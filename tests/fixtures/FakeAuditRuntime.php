<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Fixtures {
    use stdClass;

    /** Isolated in-memory read-only SQL fixture; never connects to real databases. */
    final class FakeAuditRuntime
    {
        /** @var array<string,list<array<string,mixed>>> */
        public static array $tables = [];
        public static int $queries = 0;
        public static bool $denied = false;
        public static int $activeCompany = 1;

        public static function reset(): void
        {
            self::$tables = ['documents' => [], 'transactions' => [], 'nfse_receipts' => []];
            self::$queries = 0;
            self::$denied = false;
            self::$activeCompany = 1;
        }
    }

    final class FakeAuditQuery
    {
        /** @var list<callable(array<string,mixed>):bool> */
        private array $filters = [];
        /** @var list<string> */
        private array $selection = [];
        private string $order = 'id';
        private int $limit = 100000;

        public function __construct(private string $table)
        {
            ++FakeAuditRuntime::$queries;
        }

        public function where(string $key, mixed $operator, mixed $value = null): self
        {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            $this->filters[] = static function (array $row) use ($key, $operator, $value): bool {
                return $operator === '>' ? ($row[$key] ?? null) > $value :
                    ($row[$key] ?? null) == $value;
            };
            return $this;
        }

        public function whereNull(string $key): self
        {
            $this->filters[] = static fn (array $row): bool => ($row[$key] ?? null) === null;
            return $this;
        }

        public function whereIn(string $key, array $values): self
        {
            $this->filters[] = static fn (array $row): bool => in_array($row[$key] ?? null, $values, true);
            return $this;
        }

        public function join(string $table, callable $callback): self
        {
            $callback(new class {
                public function on(string $a, string $operator, string $b): self { return $this; }
            });
            return $this;
        }

        public function orderBy(string $field): self
        {
            $this->order = $field;
            return $this;
        }

        public function limit(int $limit): self
        {
            $this->limit = $limit;
            return $this;
        }

        public function select(string ...$columns): self
        {
            $this->selection = $columns;
            return $this;
        }

        /** @return list<stdClass> */
        public function get(): array
        {
            $table = explode(' ', $this->table)[0];
            $records = FakeAuditRuntime::$tables[$table] ?? [];
            if ($table === 'nfse_receipts') {
                $joined = [];
                foreach ($records as $receipt) {
                    foreach (FakeAuditRuntime::$tables['documents'] as $doc) {
                        if (($receipt['invoice_id'] ?? 0) === $doc['id']) {
                            $joined[] = [
                                ...$receipt,
                                'd.company_id' => $doc['company_id'],
                                'd.type' => $doc['type'],
                                'd.deleted_at' => $doc['deleted_at'],
                            ];
                        }
                    }
                }
                $records = $joined;
            }
            foreach ($this->filters as $filter) {
                $records = array_values(array_filter($records, $filter));
            }
            $order = $this->order;
            usort($records, static fn (array $a, array $b): int => ($a[$order] ?? 0) <=> ($b[$order] ?? 0));
            $records = array_slice($records, 0, $this->limit);
            return array_map(function (array $record): stdClass {
                $out = [];
                foreach ($this->selection as $column) {
                    $out[substr($column, strrpos($column, '.') === false ? 0 : strrpos($column, '.') + 1)] =
                        $record[$column] ?? $record[substr($column, strrpos($column, '.') === false ? 0 : strrpos($column, '.') + 1)] ?? null;
                }
                return (object) $out;
            }, $records);
        }
    }
}

namespace Illuminate\Support\Facades {
    use Modules\AkauntingReports\Tests\Fixtures\FakeAuditQuery;
    use Modules\AkauntingReports\Tests\Fixtures\FakeAuditRuntime;

    final class DB
    {
        public static function table(string $name): FakeAuditQuery
        {
            return new FakeAuditQuery($name);
        }
    }

    final class Schema
    {
        public static function hasTable(string $table): bool
        {
            return array_key_exists($table, FakeAuditRuntime::$tables);
        }

        public static function hasColumn(string $table, string $column): bool
        {
            return $table === 'nfse_receipts' && $column === 'authorized_fiscal_snapshot';
        }
    }
}

namespace App\Utilities {
    use Modules\AkauntingReports\Tests\Fixtures\FakeAuditRuntime;

    final class Reports
    {
        public static function cannotRead(string $class): bool
        {
            return FakeAuditRuntime::$denied;
        }
    }
}

namespace {
    if (!function_exists('company_id')) {
        function company_id(): int
        {
            return \Modules\AkauntingReports\Tests\Fixtures\FakeAuditRuntime::$activeCompany;
        }
    }
    if (!function_exists('user')) {
        function user(): ?object
        {
            return \Modules\AkauntingReports\Tests\Fixtures\FakeAuditRuntime::$denied
                ? null : (object) ['id' => 1];
        }
    }
    if (!function_exists('abort')) {
        function abort(int $code): never
        {
            throw new \RuntimeException('Authorization rejected: ' . $code, $code);
        }
    }
}
