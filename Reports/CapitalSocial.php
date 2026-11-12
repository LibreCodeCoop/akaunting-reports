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
use Modules\AkauntingReports\Support\CapitalSocialPolicyResolver;
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
    public ?string $selectedRecentContribution = null;
    public ?string $selectedLoginStatus = null;
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
        // The stock report editor persists these as per-report settings.
        // Sorting preferences apply to the member summary; ledger chronology
        // retains the native date sorting.
        [$primary, $secondary, $tertiary] = $this->memberSortPreferences();
        [$defaultColumn, $defaultDirection] = $primary;

        return [
            [
                'type' => 'select',
                'name' => 'view',
                'title' => trans('akaunting-reports::ui.view'),
                'values' => ['overview' => trans('akaunting-reports::ui.by_member'), 'ledger' => trans('akaunting-reports::ui.ledger')],
                'selected' => in_array($this->model->settings->view ?? null, ['overview', 'ledger'], true)
                    ? $this->model->settings->view : 'overview',
                'placement' => 'strip',
                'attributes' => [],
            ],
            [
                'type' => 'select',
                'name' => 'default_sort',
                'title' => trans('akaunting-reports::ui.default_sort_member'),
                'values' => CapitalSocialSorter::overviewOptions(),
                'selected' => $defaultColumn,
                'attributes' => [],
            ],
            [
                'type' => 'select',
                'name' => 'default_direction',
                'title' => trans('akaunting-reports::ui.default_direction'),
                'values' => ['desc' => trans('akaunting-reports::ui.descending'), 'asc' => trans('akaunting-reports::ui.ascending')],
                'selected' => $defaultDirection,
                'attributes' => [],
            ],
            [
                'type' => 'select',
                'name' => 'secondary_sort',
                'title' => trans('akaunting-reports::ui.secondary_sort'),
                'values' => CapitalSocialSorter::overviewOptions(),
                'selected' => $secondary[0],
                'attributes' => [],
            ],
            [
                'type' => 'select',
                'name' => 'secondary_direction',
                'title' => trans('akaunting-reports::ui.secondary_direction'),
                'values' => ['desc' => trans('akaunting-reports::ui.descending'), 'asc' => trans('akaunting-reports::ui.ascending')],
                'selected' => $secondary[1],
                'attributes' => [],
            ],
            [
                'type' => 'select',
                'name' => 'tertiary_sort',
                'title' => trans('akaunting-reports::ui.tertiary_sort'),
                'values' => CapitalSocialSorter::overviewOptions(),
                'selected' => $tertiary[0],
                'attributes' => [],
            ],
            [
                'type' => 'select',
                'name' => 'tertiary_direction',
                'title' => trans('akaunting-reports::ui.tertiary_direction'),
                'values' => ['desc' => trans('akaunting-reports::ui.descending'), 'asc' => trans('akaunting-reports::ui.ascending')],
                'selected' => $tertiary[1],
                'attributes' => [],
            ],
        ];
    }

    /** @return array{array{string,string},array{string,string},array{string,string}} */
    private function memberSortPreferences(): array
    {
        return [
            CapitalSocialSorter::overviewDefaults(
                $this->model->settings->default_sort ?? null,
                $this->model->settings->default_direction ?? null
            ),
            CapitalSocialSorter::overviewSecondaryDefaults(
                $this->model->settings->secondary_sort ?? null,
                $this->model->settings->secondary_direction ?? null
            ),
            CapitalSocialSorter::overviewTertiaryDefaults(
                $this->model->settings->tertiary_sort ?? null,
                $this->model->settings->tertiary_direction ?? null
            ),
        ];
    }

    public function setFilters(): void
    {
        parent::setFilters();

        $this->filters['view'] = ['overview' => trans('akaunting-reports::ui.by_member'), 'ledger' => trans('akaunting-reports::ui.ledger')];
        $this->filters['keys']['view'] = 'view';
        $this->filters['names']['view'] = trans('akaunting-reports::ui.view');
        $savedView = (string) ($this->model->settings->view ?? 'overview');
        $this->filters['defaults']['view'] = in_array($savedView, ['overview', 'ledger'], true)
            ? $savedView : 'overview';

        $today = Date::today();
        $options = [
            $today->toDateString() => trans('general.date_range.today'),
            $today->copy()->subDay()->toDateString() => trans('general.date_range.yesterday'),
            $today->copy()->startOfMonth()->subDay()->toDateString() => trans('akaunting-reports::ui.previous_month_end'),
            $today->copy()->startOfYear()->subDay()->toDateString() => trans('akaunting-reports::ui.previous_year_end'),
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

        $search = request()->query('search', '');
        $viewWasSelected = is_string($search)
            && preg_match('/(?:^|\\s)view:(?:overview|ledger)(?:\\s|$)/', $search) === 1;
        $requestedView = $viewWasSelected ? $this->getSearchStringValue('view') : null;
        $savedView = (string) ($this->model->settings->view ?? 'overview');
        $effectiveView = $requestedView ?? $savedView;
        $this->tab = $effectiveView === 'ledger' ? 'ledger' : 'overview';
        $this->asOf = $this->getAsOfDate()->toDateString();

        $settings = DB::table('settings')->where('company_id', $companyId)
            ->whereIn('key', CapitalSocialPolicyResolver::SETTING_KEYS)->pluck('value', 'key');
        $policy = CapitalSocialPolicyResolver::resolve($companyId, default_currency(), $settings->all());
        if ($policy === null) {
            $this->configurationError = trans('akaunting-reports::ui.capital_config_error');
            return;
        }

        $all = app(CapitalSocialLedger::class)->rows($policy, null, $this->asOf);
        $this->unfilteredSummary = CapitalSocialSummary::calculate($all);
        $memberOptions = [];
        foreach ($this->unfilteredSummary['members'] as $member) {
            $memberOptions[$member['contact_id']] = $member['name'];
        }
        asort($memberOptions, SORT_NATURAL | SORT_FLAG_CASE);
        $this->filters['member'] = $memberOptions;
        $this->filters['keys']['member'] = 'member';
        $this->filters['names']['member'] = trans('akaunting-reports::ui.member');

        $member = $this->getSearchStringValue('member');
        if (is_string($member) && $member !== '') {
            if (!ctype_digit($member) || (int) $member <= 0) {
                abort(422, trans('akaunting-reports::ui.filter_invalid_member'));
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
            $this->prepareLedgerView();
        } else {
            $this->prepareMemberView($companyId);
        }
    }

    private function prepareLedgerView(): void
    {
        $this->filters['status'] = ['confirmado' => trans('akaunting-reports::ui.confirmed_plural'), 'revisar' => trans('akaunting-reports::ui.review_needed')];
        $this->filters['keys']['status'] = 'status';
        $this->filters['names']['status'] = trans('akaunting-reports::ui.confirmation');
        $this->filters['review_reason'] = [
            'awaiting_payment' => trans('akaunting-reports::ui.waiting_payment'),
            'requires_review' => trans('akaunting-reports::ui.review_required'),
        ];
        $this->filters['keys']['review_reason'] = 'review_reason';
        $this->filters['names']['review_reason'] = trans('akaunting-reports::ui.pending_reason');
        $this->filters['kind'] = [
            'integralizacao' => trans('akaunting-reports::ui.direct_contribution'),
            'retencao' => trans('akaunting-reports::ui.retained_production'),
            'devolucao' => trans('akaunting-reports::ui.refund'),
            'revisao' => trans('akaunting-reports::ui.review_needed'),
        ];
        $this->filters['keys']['kind'] = 'kind';
        $this->filters['names']['kind'] = trans('akaunting-reports::ui.movement');
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
    }

    private function prepareMemberView(int $companyId): void
    {
        $this->filters['member_review'] = [
            'any' => trans('akaunting-reports::ui.member_with_pendings'),
            'pending' => trans('akaunting-reports::ui.member_with_unconfirmed'),
            'warning' => trans('akaunting-reports::ui.member_with_warnings'),
            'clear' => trans('akaunting-reports::ui.member_clear'),
        ];
        $this->filters['keys']['member_review'] = 'member_review';
        $this->filters['names']['member_review'] = trans('akaunting-reports::ui.member_pending');
        $memberReview = $this->getSearchStringValue('member_review');
        $this->selectedMemberReview = is_string($memberReview)
            && isset($this->filters['member_review'][$memberReview])
            ? $memberReview : null;
        $this->filters['recent_contribution'] = [
            '90' => trans('akaunting-reports::ui.days_90'),
            '180' => trans('akaunting-reports::ui.days_180'),
            '365' => trans('akaunting-reports::ui.days_365'),
            'older' => trans('akaunting-reports::ui.days_older'),
        ];
        $this->filters['keys']['recent_contribution'] = 'recent_contribution';
        $this->filters['names']['recent_contribution'] = trans('akaunting-reports::ui.financial_activity');
        $recent = $this->getSearchStringValue('recent_contribution');
        $this->selectedRecentContribution = is_string($recent)
            && isset($this->filters['recent_contribution'][$recent])
            ? $recent : null;
        $asOf = Date::parse($this->asOf);
        $cutoffs = [
            '90' => $asOf->copy()->subDays(90)->toDateString(),
            '180' => $asOf->copy()->subDays(180)->toDateString(),
            '365' => $asOf->copy()->subDays(365)->toDateString(),
        ];
        // This shows a real, company-scoped Akaunting account association.
        // A login being enabled is NOT proof of active cooperative membership.
        $contactIds = array_column($this->capitalSummary['members'], 'contact_id');
        $linkedLogins = empty($contactIds)
            ? collect()
            : DB::table('contacts as contact')
                ->leftJoin('users as account', 'account.id', '=', 'contact.user_id')
                ->leftJoin('user_companies as membership', function ($join) use ($companyId): void {
                    $join->on('membership.user_id', '=', 'account.id')
                        ->where('membership.company_id', '=', $companyId);
                })
                ->where('contact.company_id', $companyId)
                ->whereIn('contact.id', $contactIds)
                ->select(
                    'contact.id as contact_id', 'contact.user_id',
                    'account.enabled as account_enabled',
                    'account.deleted_at as account_deleted',
                    'membership.user_id as assigned_user_id'
                )
                ->get()->keyBy('contact_id');
        $membersWithAccess = [];
        foreach ($this->capitalSummary['members'] as $member) {
            $linked = $linkedLogins->get($member['contact_id']);
            $member['login_status'] = !$linked || !$linked->user_id
                ? 'unlinked'
                : (($linked->account_enabled && !$linked->account_deleted && $linked->assigned_user_id)
                    ? 'enabled' : 'disabled');
            $membersWithAccess[] = $member;
        }
        $this->filters['login_status'] = [
            'enabled' => trans('akaunting-reports::ui.login_enabled'),
            'disabled' => trans('akaunting-reports::ui.login_disabled'),
            'unlinked' => trans('akaunting-reports::ui.login_absent'),
        ];
        $this->filters['keys']['login_status'] = 'login_status';
        $this->filters['names']['login_status'] = trans('akaunting-reports::ui.account_access');
        $login = $this->getSearchStringValue('login_status');
        $this->selectedLoginStatus = is_string($login)
            && isset($this->filters['login_status'][$login])
            ? $login : null;

        $members = array_values(array_filter(
            $membersWithAccess,
            function (array $member) use ($cutoffs): bool {
                $hasReview = match ($this->selectedMemberReview) {
                    'any' => $member['pending'] > 0 || $member['warnings'] > 0,
                    'pending' => $member['pending'] > 0,
                    'warning' => $member['warnings'] > 0,
                    'clear' => $member['pending'] === 0 && $member['warnings'] === 0,
                    default => true,
                };
                $last = $member['last_contribution_date'];
                $hasActivity = match ($this->selectedRecentContribution) {
                    '90', '180', '365' => $last !== null
                        && $last >= $cutoffs[$this->selectedRecentContribution],
                    'older' => $last === null || $last < $cutoffs['365'],
                    default => true,
                };

                $hasAccess = $this->selectedLoginStatus === null
                    || $member['login_status'] === $this->selectedLoginStatus;

                return $hasReview && $hasActivity && $hasAccess;
            }
        ));
        if ($this->selectedMemberReview !== null || $this->selectedRecentContribution !== null
            || $this->selectedLoginStatus !== null) {
            // Financial indicators must reflect the same selection as the table.
            $allowed = array_fill_keys(array_column($members, 'identity'), true);
            $selectedMovements = array_values(array_filter(
                $this->movements,
                static fn (array $row): bool => isset($allowed[(string) $row['identity']])
            ));
            $this->capitalSummary = CapitalSocialSummary::calculate($selectedMovements);
            $this->reviewCounts = CapitalSocialReview::counts($selectedMovements);
        }
        [$primary, $secondary, $tertiary] = $this->memberSortPreferences();
        [$defaultColumn, $defaultDirection] = $primary;
        // The native header determines only the first sorting criterion.
        // The saved second and third criteria always break ties.
        if (!request()->has('sort')) {
            request()->merge(['sort' => $defaultColumn, 'direction' => $defaultDirection]);
        }
        $this->sortedMembers = CapitalSocialSorter::sort(
            $members, 'overview', request()->query('sort'), request()->query('direction'),
            $defaultColumn, $defaultDirection,
            [
                ['column' => $secondary[0], 'direction' => $secondary[1]],
                ['column' => $tertiary[0], 'direction' => $tertiary[1]],
            ]
        );
        $this->memberPages = $this->paginate($this->sortedMembers, 15);
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
            if (preg_match('/^(view|member|member_review|recent_contribution|login_status):/', $token)) {
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

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                throw new \RuntimeException('Cannot open export stream.');
            }
            if ($this->tab === 'overview') {
                fputcsv($out, [trans('akaunting-reports::ui.member'), trans('akaunting-reports::ui.direct_contribution') . ' (' . default_currency() . ')', trans('akaunting-reports::ui.withholdings') . ' (' . default_currency() . ')', trans('akaunting-reports::ui.refunds') . ' (' . default_currency() . ')', trans('akaunting-reports::ui.balance') . ' (' . default_currency() . ')', trans('akaunting-reports::ui.activity_confirmed'), trans('akaunting-reports::ui.pending'), trans('akaunting-reports::ui.status')]);
                foreach ($this->sortedMembers as $member) {
                    fputcsv($out, [
                        self::safeCsv((string) $member['name']),
                        self::decimal((int) $member['direct_cents']),
                        self::decimal((int) $member['retained_cents']),
                        self::decimal((int) $member['devolvido_cents']),
                        self::decimal((int) $member['saldo_cents']),
                        (string) ($member['last_contribution_date'] ?? ''),
                        (int) $member['pending'],
                        self::safeCsv($member['status_labels'] === []
                            ? trans('akaunting-reports::ui.no_occurrences')
                            : implode('; ', array_map(
                                static fn (string $label, int $count): string => $label . ' (' . $count . ')',
                                array_keys($member['status_labels']),
                                array_values($member['status_labels'])
                            ))),
                    ]);
                }
            } else {
                fputcsv($out, [trans('akaunting-reports::ui.date'), trans('akaunting-reports::ui.member'), trans('akaunting-reports::ui.kind'), trans('akaunting-reports::ui.confirmed_value') . ' (' . default_currency() . ')', trans('akaunting-reports::ui.pending_value') . ' (' . default_currency() . ')', trans('akaunting-reports::ui.status'), trans('akaunting-reports::ui.source'), trans('akaunting-reports::ui.document'), trans('akaunting-reports::ui.observation'), trans('akaunting-reports::ui.individual_balance') . ' (' . default_currency() . ')']);
                foreach ($this->movements as $row) {
                    fputcsv($out, [
                        (string) $row['date'], self::safeCsv((string) $row['name']),
                        trans('akaunting-reports::ui.kind_' . $row['kind']), self::decimal((int) $row['amount_cents']),
                        $row['status'] === 'revisar' ? self::decimal((int) $row['candidate_cents']) : '',
                        trans('akaunting-reports::ui.status_' . $row['status']), trans('akaunting-reports::ui.source_' . $row['source']), (int) $row['id'],
                        self::safeCsv(CapitalSocialReview::reason((string) $row['reason'])),
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
