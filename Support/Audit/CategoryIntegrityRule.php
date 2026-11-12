<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

/**
 * Native category semantics only: explicit opposite income/expense types.
 * Missing or module-specific types do not establish a bookkeeping error.
 */
final class CategoryIntegrityRule implements Rule
{
    #[\Override]
    public function id(): string { return 'category_integrity'; }

    #[\Override]
    public function version(): int { return 1; }

    #[\Override]
    public function sources(): array { return ['documents', 'categories']; }

    /** @return list<Occurrence> */
    #[\Override]
    public function inspect(Snapshot $snapshot): array
    {
        $categories = [];
        foreach ($snapshot->rows('categories') as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id > 0) {
                $categories[$id] = (string) ($category['type'] ?? '');
            }
        }

        $result = [];
        foreach ($snapshot->rows('documents') as $document) {
            $id = (int) ($document['id'] ?? 0);
            $categoryId = (int) ($document['category_id'] ?? 0);
            $type = (string) ($document['type'] ?? '');
            $status = (string) ($document['status'] ?? '');
            if ($id <= 0 || $categoryId <= 0 || !in_array($type, ['invoice', 'bill'], true)
                || in_array($status, ['draft', 'cancelled', 'canceled', 'void'], true)) {
                continue;
            }

            $evidence = ['category_id' => $categoryId];
            $case = null;
            $classification = Occurrence::SUSPECTED;
            $severity = 'warning';
            if (!array_key_exists($categoryId, $categories)) {
                // The record may have been soft-deleted or reclassified historically.
                $case = 'category_unavailable';
                $classification = Occurrence::INSUFFICIENT;
                $severity = 'info';
            } elseif (($type === 'invoice' && $categories[$categoryId] === 'expense')
                || ($type === 'bill' && $categories[$categoryId] === 'income')) {
                $case = 'category_opposite_direction';
            }
            // Other category types may be extended by modules; do not assume invalidity.
            if ($case !== null) {
                $result[] = new Occurrence(
                    $snapshot->companyId, $this->id(), $this->version(),
                    $classification, $severity, 'document', (string) $id, $case,
                    is_string($document['competence'] ?? null) ? $document['competence'] : null,
                    $evidence, 'audit.' . $case, 'audit.review_source'
                );
            }
        }
        return $result;
    }
}
