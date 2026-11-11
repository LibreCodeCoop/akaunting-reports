<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Reports;

use App\Abstracts\Report;
use App\Utilities\Date;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Support\FrraAnnualLedger;
use Modules\AkauntingReports\Support\FrraAnnualMatrix;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Annual FRRA retained by member and competence, independent of payment date. */
final class FrraAnnual extends Report
{
    public $default_name = 'akaunting-reports::reports.frra_annual';
    public $category = 'general.accounting';
    public $icon = 'date_range';
    public $type = 'frra';
    public $chart = false;
    public $has_money = false;
    public $cacheable = false;

    public int $selectedYear;
    public ?int $selectedMember = null;
    /** @var array<string,mixed> */
    public array $summary = [];
    /** @var array<string,mixed> */
    public array $issues = [];
    public ?string $configurationError = null;
    /** @var array<string,mixed>|null */
    private ?array $sourceLedger = null;
    /** @var array<int,string> */
    public array $monthNames = [
        1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
        5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
    ];

    public function setViews(): void
    {
        parent::setViews();
        $this->views['frra'] = 'akaunting-reports::reports.frra-annual.content';
        $this->views['print'] = 'akaunting-reports::reports.frra-annual.print';
    }

    public function setTables(): void
    {
        $this->tables = [];
    }

    public function setDates(): void
    {
        // The item competence, not a document issue date, determines its month.
    }

    public function getFields(): array
    {
        return [[
            'type' => 'select',
            'name' => 'year',
            'title' => 'Ano de competência',
            'values' => $this->yearOptions(),
            'selected' => $this->defaultYear(),
            'placement' => 'strip',
            'attributes' => [],
        ]];
    }

    public function setFilters(): void
    {
        parent::setFilters();
        $this->filters['year'] = $this->yearOptions();
        $this->filters['keys']['year'] = 'year';
        $this->filters['names']['year'] = 'Ano de competência';
        $this->filters['defaults']['year'] = (string) $this->defaultYear();
        $this->filters['operators']['year'] = [
            'equal' => true, 'not_equal' => false, 'multiple' => false, 'range' => false,
        ];
        // The native member selector must match the actual FRRA report sources,
        // not Akaunting's entire vendors directory (which also has duplicates).
        $ledger = $this->readSources((int) company_id());
        $this->filters['member'] = $ledger === null ? []
            : FrraAnnualMatrix::memberOptions($ledger['entries'], $this->requestedYear());
        $this->filters['keys']['member'] = 'member';
        $this->filters['names']['member'] = 'Cooperado';
        $this->filters['operators']['member'] = [
            'equal' => true, 'not_equal' => false, 'multiple' => false, 'range' => false,
        ];
    }

    public function setData(): void
    {
        $company = (int) company_id();
        if ($company <= 0) {
            throw new \RuntimeException('A company context is required for FRRA reporting.');
        }
        $this->selectedYear = $this->requestedYear();
        $rawMember = $this->getSearchStringValue('member');
        $this->selectedMember = is_string($rawMember) && ctype_digit($rawMember)
            && (int) $rawMember > 0 ? (int) $rawMember : null;

        $ledger = $this->readSources($company);
        if ($ledger === null) {
            $this->configurationError = 'Configure as origens de FRRA desta empresa antes de utilizar o relatório.';
            $this->summary = FrraAnnualMatrix::build([], $this->selectedYear);
            return;
        }
        $entries = $ledger['entries'];
        if ($this->selectedMember !== null) {
            $entries = array_values(array_filter(
                $entries, fn (array $row): bool => $row['contact_id'] === $this->selectedMember
            ));
        }
        $this->summary = FrraAnnualMatrix::build($entries, $this->selectedYear);
        $this->issues = array_diff_key($ledger, ['entries' => true]);
    }

    /**
     * Share the same company-scoped source between the native filter and the
     * financial matrix within one report request. Avoids divergent queries
     * and double-loading the full document ledger.
     *
     * @return array<string,mixed>|null
     */
    private function readSources(int $company): ?array
    {
        if ($company <= 0) {
            return null;
        }
        if ($this->sourceLedger !== null) {
            return $this->sourceLedger;
        }

        $settings = DB::table('settings')->where('company_id', $company)
            ->whereIn('key', [
                'akaunting-reports.frra.production_category_id',
                'akaunting-reports.frra.annual_category_id',
                'akaunting-reports.frra.production_prefix',
                'akaunting-reports.frra.annual_prefix',
                'akaunting-reports.frra.item_name',
            ])->pluck('value', 'key');

        $p = 'akaunting-reports.frra.';
        $policy = [
            'production_category_id' => (int) ($settings[$p . 'production_category_id'] ?? 0),
            'annual_category_id' => (int) ($settings[$p . 'annual_category_id'] ?? 0),
            'production_prefix' => (string) ($settings[$p . 'production_prefix'] ?? ''),
            'annual_prefix' => (string) ($settings[$p . 'annual_prefix'] ?? ''),
            'item_name' => (string) ($settings[$p . 'item_name'] ?? ''),
        ];
        if ($policy['production_category_id'] <= 0 || $policy['annual_category_id'] <= 0
            || $policy['production_prefix'] === '' || $policy['annual_prefix'] === ''
            || $policy['item_name'] === '') {
            return null;
        }

        return $this->sourceLedger = FrraAnnualLedger::read($company, $policy);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                throw new \RuntimeException('Cannot open CSV stream.');
            }
            $columns = ['Cooperado'];
            foreach ($this->monthNames as $month) {
                $columns[] = $month . '/' . $this->selectedYear . ' (BRL)';
            }
            $columns[] = 'Total anual (BRL)';
            $columns[] = 'Retenção na produção (BRL)';
            $columns[] = 'Complemento da fatura anual (BRL)';
            fputcsv($out, $columns);
            foreach ($this->summary['members'] as $member) {
                $values = [self::safeCsv((string) $member['name'])];
                foreach ($member['months'] as $cents) {
                    $values[] = self::decimal((int) $cents);
                }
                $values[] = self::decimal($member['total_cents']);
                $values[] = self::decimal($member['production_cents']);
                $values[] = self::decimal($member['annual_cents']);
                fputcsv($out, $values);
            }
            $totals = ['TOTAL'];
            foreach ($this->summary['months'] as $cents) {
                $totals[] = self::decimal((int) $cents);
            }
            $totals[] = self::decimal($this->summary['total_cents']);
            $totals[] = self::decimal($this->summary['production_cents']);
            $totals[] = self::decimal($this->summary['annual_cents']);
            fputcsv($out, $totals);
            fclose($out);
        }, 'frra-anual-' . $this->selectedYear . '.csv',
        ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function requestedYear(): int
    {
        $value = $this->getSearchStringValue('year');
        $years = $this->yearOptions();
        return is_string($value) && isset($years[$value])
            ? (int) $value : $this->defaultYear();
    }

    private function configuredYear(): int
    {
        $value = $this->model->settings->year ?? null;
        return is_string($value) && preg_match('/^20\d{2}$/D', $value)
            && (int) $value <= 2100 ? (int) $value : (int) Date::today()->year;
    }

    private function defaultYear(): int
    {
        $years = $this->yearOptions();
        $saved = $this->configuredYear();
        if (isset($years[$saved])) {
            return $saved;
        }
        $current = (int) Date::today()->year;
        return isset($years[$current]) ? $current : (int) array_key_first($years);
    }

    /** @return array<int,string> */
    private function yearOptions(): array
    {
        $ledger = $this->readSources((int) company_id());
        return FrraAnnualMatrix::yearOptions(
            $ledger['entries'] ?? [],
            $ledger['unallocated_by_year'] ?? [],
            (int) Date::today()->year
        );
    }

    private static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function safeCsv(string $name): string
    {
        return preg_match('/^[=+@\-\t\r]/u', $name) ? "'" . $name : $name;
    }
}
