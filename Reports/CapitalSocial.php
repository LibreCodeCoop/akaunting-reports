<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Reports;

use App\Abstracts\Report;
use App\Utilities\Date;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Support\CapitalSocialLedger;
use Modules\AkauntingReports\Support\CapitalSocialPolicy;
use Modules\AkauntingReports\Support\CapitalSocialReview;
use Modules\AkauntingReports\Support\CapitalSocialSorter;
use Modules\AkauntingReports\Support\CapitalSocialSummary;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A stock balance report using Akaunting's standard report layout and filter bar.
 * Only the domain-specific content is custom; the ledger remains read-only.
 */
final class CapitalSocial extends Report
{
    public $default_name = 'akaunting-reports::reports.capital_social';
    public $category = 'general.accounting';
    public $icon = 'account_balance';
    public $type = 'capital';
    public $chart = false;
    public $has_money = false;
    public $cacheable = false;

    /** @var list<array<string,mixed>> */
    public array $movements = [];
    /** @var array<string,mixed> */
    public array $capitalSummary = [];
    /** @var array<string,mixed> */
    public array $unfilteredSummary = [];
    public ?LengthAwarePaginator $memberPages = null;
    public ?LengthAwarePaginator $movementPages = null;
    public string $tab = 'overview';
    public ?string $asOf = null;
    public ?int $selectedMember = null;
    public ?string $selectedStatus = null;
    public ?string $selectedKind = null;
    public ?string $selectedReviewReason = null;
    public ?string $selectedMemberReview = null;
    /** @var list<array<string,mixed>> */
    public array $sortedMembers = [];
    /** @var array{confirmed:int,awaiting_payment:int,requires_review:int} */
    public array $reviewCounts = ['confirmed' => 0, 'awaiting_payment' => 0, 'requires_review' => 0];
    public ?string $configurationError = null;

    public function setViews(): void
    {
        parent::setViews();
        // Keep "show" and "filter" from the Akaunting core.
        $this->views['capital'] = 'akaunting-reports::reports.capital-social.content';
        $this->views['print'] = 'akaunting-reports::reports.capital-social.print';
    }

    public function setTables(): void
    {
        $this->tables = [];
    }

    public function setDates(): void
    {
        // A capital balance is measured as of one date, not over reporting periods.
    }

    public function getFields(): array
    {
        // Akaunting's own filter bar owns this view switch and preserves it when
        // applying date, member and other filters. No custom URL controller.
        return [[
            'type' => 'select',
            'name' => 'view',
            'title' => 'Visualização',
            'values' => ['overview' => 'Por cooperado', 'ledger' => 'Extrato'],
            'selected' => 'overview',
            'placement' => 'strip',
        ]];
    }

    public function setFilters(): void
    {
        parent::setFilters();

        $this->filters['view'] = ['overview' => 'Por cooperado', 'ledger' => 'Extrato'];
        $this->filters['keys']['view'] = 'view';
        $this->filters['names']['view'] = 'Visualização';
        $this->filters['defaults']['view'] = 'overview';

        $today = Date::today();
        $options = [
            $today->toDateString() => trans('general.date_range.today'),
            $today->copy()->subDay()->toDateString() => trans('general.date_range.yesterday'),
            $today->copy()->startOfMonth()->subDay()->toDateString() => 'Fim do mês anterior',
            $today->copy()->startOfYear()->subDay()->toDateString() => 'Fim do ano anterior',
        ];
        $selected = $this->getAsOfDate()->toDateString();
        $options[$selected] ??= company_date($selected);
        $this->filters['as_of'] = $options;
        $this->filters['keys']['as_of'] = 'as_of';
        $this->filters['names']['as_of'] = trans('reports.as_of');
        $this->filters['defaults']['as_of'] = $selected;
        $this->filters['operators']['as_of'] = [
            'equal' => true, 'not_equal' => false, 'multiple' => false, 'range' => false,
        ];
    }

    public function setData(): void
    {
        $companyId = (int) company_id();
        if ($companyId <= 0) {
            throw new \RuntimeException('An active company is required.');
        }

        $this->tab = $this->getSearchStringValue('view') === 'ledger' ? 'ledger' : 'overview';
        $this->asOf = $this->getAsOfDate()->toDateString();

        $settings = DB::table('settings')->where('company_id', $companyId)
            ->whereIn('key', [
                'akaunting-reports.capital.category_id',
                'akaunting-reports.capital.item_id',
                'akaunting-reports.capital.refund_code',
                'akaunting-reports.capital.advance_category_id',
                'akaunting-reports.capital.advance_item_id',
                'akaunting-reports.capital.legacy_refund_category_id',
                'akaunting-reports.capital.late_deduction_item_id',
                'akaunting-reports.capital.excluded_expense_category_id',
            ])->pluck('value', 'key');

        $categoryId = (int) ($settings['akaunting-reports.capital.category_id'] ?? 0);
        $itemId = (int) ($settings['akaunting-reports.capital.item_id'] ?? 0);
        if ($categoryId <= 0 || $itemId <= 0) {
            $this->configurationError = 'Configure a categoria e o item de capital social para esta empresa.';
            return;
        }

        $policy = new CapitalSocialPolicy(
            companyId: $companyId,
            categoryId: $categoryId,
            itemId: $itemId,
            refundCategoryCode: (string) ($settings['akaunting-reports.capital.refund_code'] ?? 'capital-social-refund'),
            advanceCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.advance_category_id'] ?? null),
            advanceItemId: self::positiveOrNull($settings['akaunting-reports.capital.advance_item_id'] ?? null),
            legacyRefundCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.legacy_refund_category_id'] ?? null),
            lateDeductionItemId: self::positiveOrNull($settings['akaunting-reports.capital.late_deduction_item_id'] ?? null),
            excludedExpenseCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.excluded_expense_category_id'] ?? null),
        );

        $all = app(CapitalSocialLedger::class)->rows($policy, null, $this->asOf);
        $this->unfilteredSummary = CapitalSocialSummary::calculate($all);
        $memberOptions = [];
        foreach ($this->unfilteredSummary['members'] as $member) {
            $memberOptions[$member['contact_id']] = $member['name'];
        }
        asort($memberOptions, SORT_NATURAL | SORT_FLAG_CASE);
        $this->filters['member'] = $memberOptions;
        $this->filters['keys']['member'] = 'member';
        $this->filters['names']['member'] = 'Cooperado';

        $member = $this->getSearchStringValue('member');
        if (is_string($member) && $member !== '') {
            if (!ctype_digit($member) || (int) $member <= 0) {
                abort(422, 'Filtro de cooperado inválido.');
            }
            $this->selectedMember = (int) $member;
        }

        $this->movements = array_values(array_filter(
            $all,
            fn (array $row): bool => $this->selectedMember === null || $row['contact_id'] === $this->selectedMember
        ));
        $this->capitalSummary = CapitalSocialSummary::calculate($this->movements);
        $this->reviewCounts = CapitalSocialReview::counts($this->movements);

        if ($this->tab === 'ledger') {
            $this->filters['status'] = ['confirmado' => 'Confirmadas', 'revisar' => 'A conferir'];
            $this->filters['keys']['status'] = 'status';
            $this->filters['names']['status'] = 'Confirmação';
            $this->filters['review_reason'] = [
                'awaiting_payment' => 'Aguardando pagamento',
                'requires_review' => 'Conferência necessária',
            ];
            $this->filters['keys']['review_reason'] = 'review_reason';
            $this->filters['names']['review_reason'] = 'Motivo da pendência';
            $this->filters['kind'] = [
                'integralizacao' => 'Integralização direta',
                'retencao' => 'Retenção na produção',
                'devolucao' => 'Devolução',
                'revisao' => 'A conferir',
            ];
            $this->filters['keys']['kind'] = 'kind';
            $this->filters['names']['kind'] = 'Movimentação';
            $status = $this->getSearchStringValue('status');
            $kind = $this->getSearchStringValue('kind');
            $this->selectedStatus = is_string($status) && isset($this->filters['status'][$status]) ? $status : null;
            $this->selectedKind = is_string($kind) && isset($this->filters['kind'][$kind]) ? $kind : null;
            $reviewReason = $this->getSearchStringValue('review_reason');
            $this->selectedReviewReason = is_string($reviewReason)
                && isset($this->filters['review_reason'][$reviewReason])
                ? $reviewReason : null;

            $balances = [];
            usort($this->movements, static fn (array $a, array $b): int =>
                [$a['date'], $a['source'], $a['id']] <=> [$b['date'], $b['source'], $b['id']]
            );
            foreach ($this->movements as &$row) {
                $id = (int) $row['contact_id'];
                $balances[$id] ??= 0;
                if ($row['status'] === 'confirmado') {
                    $balances[$id] += (int) $row['amount_cents'];
                }
                $row['running_balance_cents'] = $balances[$id];
            }
            unset($row);
            $this->movements = array_values(array_filter($this->movements, fn (array $row): bool =>
                ($this->selectedStatus === null || $row['status'] === $this->selectedStatus)
                && ($this->selectedKind === null || $row['kind'] === $this->selectedKind)
                && ($this->selectedReviewReason === null
                    || CapitalSocialReview::category($row) === $this->selectedReviewReason)
            ));
            $this->movements = CapitalSocialSorter::sort(
                $this->movements, 'ledger', request()->query('sort'), request()->query('direction')
            );
            $this->movementPages = $this->paginate($this->movements, 25);
        } else {
            $this->filters['member_review'] = [
                'pending' => 'Com valores não confirmados',
                'warning' => 'Com ressalvas',
                'clear' => 'Sem pendências ou ressalvas',
            ];
            $this->filters['keys']['member_review'] = 'member_review';
            $this->filters['names']['member_review'] = 'Pendências por cooperado';
            $memberReview = $this->getSearchStringValue('member_review');
            $this->selectedMemberReview = is_string($memberReview)
                && isset($this->filters['member_review'][$memberReview])
                ? $memberReview : null;
            $members = array_values(array_filter(
                $this->capitalSummary['members'],
                fn (array $member): bool => match ($this->selectedMemberReview) {
                    'pending' => $member['pending'] > 0,
                    'warning' => $member['warnings'] > 0,
                    'clear' => $member['pending'] === 0 && $member['warnings'] === 0,
                    default => true,
                }
            ));
            // Match Akaunting's sortable links: the initially selected order
            // must be visible in the column header, not only applied internally.
            // Only set a default when the user has not selected any column.
            if (!request()->has('sort')) {
                request()->merge(['sort' => 'pending', 'direction' => 'desc']);
            }
            $this->sortedMembers = CapitalSocialSorter::sort(
                $members, 'overview', request()->query('sort'), request()->query('direction')
            );
            $this->memberPages = $this->paginate($this->sortedMembers, 15);
        }
    }

    /** @param list<array<string,mixed>> $rows */
    private function paginate(array $rows, int $perPage): LengthAwarePaginator
    {
        $page = filter_var(request()->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        return (new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => route('reports.show', $this->model->id)]
        ))->appends(request()->except('page'));
    }

    public function reportUrl(string $tab, ?int $memberId = null, ?string $status = null): string
    {
        // Reuse the exact native report filter string; only change the view
        // and (if requested) the member/status selection.
        $search = request()->query('search', '');
        $tokens = is_string($search) ? preg_split('/\s+/', trim($search)) : [];
        $tokens = array_values(array_filter($tokens ?: [], static function (string $token) use ($tab, $status): bool {
            if (preg_match('/^(view|member|member_review):/', $token)) {
                return false;
            }
            if ($tab !== 'ledger' && preg_match('/^(kind|status|review_reason):/', $token)) {
                return false;
            }
            return $status === null || !str_starts_with($token, 'status:');
        }));
        $tokens[] = 'view:' . ($tab === 'ledger' ? 'ledger' : 'overview');
        if ($this->asOf !== null && !array_filter($tokens, static fn (string $token): bool =>
            str_starts_with($token, 'as_of:'))) {
            $tokens[] = 'as_of:' . $this->asOf;
        }
        if ($memberId !== null && $memberId > 0) {
            $tokens[] = 'member:' . $memberId;
        }
        if ($status !== null && in_array($status, ['confirmado', 'revisar'], true)) {
            $tokens[] = 'status:' . $status;
        }

        $query = request()->except(['search', 'sort', 'direction', 'page', 'tab']);
        $query['search'] = implode(' ', $tokens);
        return route('reports.show', $this->model->id) . '?' . http_build_query($query);
    }

    private static function positiveOrNull(mixed $value): ?int
    {
        return $value !== null && (int) $value > 0 ? (int) $value : null;
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                throw new \RuntimeException('Cannot open export stream.');
            }
            if ($this->tab === 'overview') {
                fputcsv($out, ['Cooperado', 'Integralização direta (BRL)', 'Retenções (BRL)', 'Devoluções (BRL)', 'Saldo (BRL)', 'Pendências']);
                foreach ($this->sortedMembers as $member) {
                    fputcsv($out, [
                        self::safeCsv((string) $member['name']),
                        self::decimal((int) $member['direct_cents']),
                        self::decimal((int) $member['retained_cents']),
                        self::decimal((int) $member['devolvido_cents']),
                        self::decimal((int) $member['saldo_cents']),
                        (int) $member['pending'],
                    ]);
                }
            } else {
                fputcsv($out, ['Data', 'Cooperado', 'Natureza', 'Valor confirmado (BRL)', 'Valor em análise (BRL)', 'Situação', 'Origem', 'Documento', 'Observação', 'Saldo individual (BRL)']);
                foreach ($this->movements as $row) {
                    fputcsv($out, [
                        (string) $row['date'], self::safeCsv((string) $row['name']),
                        (string) $row['kind'], self::decimal((int) $row['amount_cents']),
                        $row['status'] === 'revisar' ? self::decimal((int) $row['candidate_cents']) : '',
                        (string) $row['status'], (string) $row['source'], (int) $row['id'],
                        self::safeCsv((string) $row['reason']),
                        $this->selectedMember !== null ? self::decimal((int) $row['running_balance_cents']) : '',
                    ]);
                }
            }
            fclose($out);
        }, 'capital-social-' . $this->tab . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function safeCsv(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/u', $value) ? "'" . $value : $value;
    }
}
