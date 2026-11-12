{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}

{{-- Included INSIDE components.reports.show: Akaunting owns the toolbar, filters and page layout. --}}
<section class="my-6">
    @if ($class->configurationError)
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            {{ $class->configurationError }}
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-black-400">{{ trans('akaunting-reports::ui.capital_position') }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ trans('akaunting-reports::ui.as_of') }} {{ company_date($class->asOf) }} ·
                    {{ $class->capitalSummary['members_count'] }} {{ trans('akaunting-reports::ui.members_count') }} ·
                    {{ trans('akaunting-reports::ui.read_only_activity') }}
                </p>
            </div>
            @if ($class->selectedMember !== null)
                <a href="{{ $class->reportUrl('overview') }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
                    <span class="material-icons text-base" aria-hidden="true">arrow_back</span>
                    {{ trans('akaunting-reports::ui.back_overview') }}
                </a>
            @endif
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">account_balance_wallet</span>
                    {{ trans('akaunting-reports::ui.confirmed_balance') }}
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    {{ money(($class->capitalSummary['total_cents']) / 100, default_currency(), true) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ trans('akaunting-reports::ui.net_balance_detail') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">add_circle_outline</span>
                    {{ trans('akaunting-reports::ui.direct_contribution') }}
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    {{ money(($class->capitalSummary['direct_cents']) / 100, default_currency(), true) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ trans('akaunting-reports::ui.received_direct') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">receipt_long</span>
                    {{ trans('akaunting-reports::ui.short_retained_production') }}
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    {{ money(($class->capitalSummary['retained_cents']) / 100, default_currency(), true) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ trans('akaunting-reports::ui.member_deduction') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">remove_circle_outline</span>
                    {{ trans('akaunting-reports::ui.capital_refunded') }}
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    {{ money(($class->capitalSummary['devolvido_cents']) / 100, default_currency(), true) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ trans('akaunting-reports::ui.refunds_detail') }}</p>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-center gap-3">
                @if ($class->capitalSummary['pending_count'] > 0)
                    <span class="material-icons text-yellow-600" aria-hidden="true">info_outline</span>
                @else
                    <span class="material-icons text-green-600" aria-hidden="true">check_circle_outline</span>
                @endif
                <p class="text-sm text-black-400">
                    <strong>{{ $class->capitalSummary['confirmed_count'] }}</strong> {{ trans('akaunting-reports::ui.confirmed_movements') }} ·
                    <strong>{{ $class->reviewCounts['awaiting_payment'] }}</strong> {{ trans('akaunting-reports::ui.awaiting_payment_movements') }} ·
                    <strong>{{ $class->reviewCounts['requires_review'] }}</strong> {{ trans('akaunting-reports::ui.require_review') }}.
                    @if ($class->capitalSummary['warnings_count'] > 0)
                        <strong>{{ $class->capitalSummary['warnings_count'] }}</strong> {{ trans('akaunting-reports::ui.audit_reservations') }}.
                    @endif
                    <span class="text-gray-500">{{ trans('akaunting-reports::ui.pending_exclusion_help') }}</span>
                </p>
            </div>
            @if ($class->capitalSummary['pending_count'] > 0)
                <a href="{{ $class->reportUrl('ledger', $class->selectedMember, 'revisar') }}" class="text-sm font-medium text-blue-600 hover:underline">
                    {{ trans('akaunting-reports::ui.view_ledger') }}
                </a>
            @endif
        </div>

        <nav aria-label="{{ trans('akaunting-reports::ui.views_nav') }}" class="mb-6 flex flex-wrap gap-3 border-b border-gray-200">
            <a href="{{ $class->reportUrl('overview') }}"
               class="{{ $class->tab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-black-400' }} inline-flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-medium"
               @if ($class->tab === 'overview') aria-current="page" @endif>
                <span class="material-icons text-lg" aria-hidden="true">people_outline</span>
                {{ trans('akaunting-reports::ui.by_member') }}
            </a>
            <a href="{{ $class->reportUrl('ledger', $class->selectedMember) }}"
               class="{{ $class->tab === 'ledger' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-black-400' }} inline-flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-medium"
               @if ($class->tab === 'ledger') aria-current="page" @endif>
                <span class="material-icons text-lg" aria-hidden="true">list_alt</span>
                {{ trans('akaunting-reports::ui.movement_ledger') }}
            </a>
        </nav>

        @if ($class->tab === 'overview')
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold text-black-400">{{ trans('akaunting-reports::ui.capital_by_member') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ trans('akaunting-reports::ui.overview_help') }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ trans('akaunting-reports::ui.last_contribution_help') }}</p>
                </div>
                <span class="text-sm text-gray-500">{{ count($class->sortedMembers) }} {{ trans('akaunting-reports::ui.members_found') }}</span>
            </div>

            {{-- Keep Akaunting's sortable links available when desktop headings are stacked away. --}}
            <div class="mb-3 flex flex-wrap items-center gap-3 text-xs lg:hidden" aria-label="{{ trans('akaunting-reports::ui.sort_order') }}">
                <span class="text-gray-500">{{ trans('akaunting-reports::ui.sort') }}</span>
                <x-sortablelink column="last_contribution_date" title="{{ trans('akaunting-reports::ui.recent') }}" />
                <x-sortablelink column="pending" title="{{ trans('akaunting-reports::ui.pending_with_warnings') }}" />
                <x-sortablelink column="saldo_cents" title="{{ trans('akaunting-reports::ui.balance') }}" />
                <details class="w-full text-xs text-gray-500">
                    <summary class="cursor-pointer">{{ trans('akaunting-reports::ui.sort_more') }}</summary>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <x-sortablelink column="name" title="{{ trans('akaunting-reports::ui.member') }}" />
                        <x-sortablelink column="direct_cents" title="{{ trans('akaunting-reports::ui.direct_short') }}" />
                        <x-sortablelink column="retained_cents" title="{{ trans('akaunting-reports::ui.retained_short') }}" />
                        <x-sortablelink column="devolvido_cents" title="{{ trans('akaunting-reports::ui.refunds') }}" />
                    </div>
                </details>
            </div>

            <div class="mb-4 rounded-lg border border-gray-200 bg-white">
                <x-table aria-label="{{ trans('akaunting-reports::ui.capital_position_table') }}" style="width:100%;">
                    <x-table.thead class="hidden lg:block">
                        <x-table.tr>
                            <x-table.th override="class" class="lg:w-3/12 min-w-0 px-2 py-3 text-xs font-medium text-black text-left rtl:text-right">
                                <x-slot name="first">
                                    <x-sortablelink column="name" title="{{ trans('akaunting-reports::ui.member') }}" />
                                </x-slot>
                                <x-slot name="second">
                                    <x-sortablelink column="last_contribution_date" title="{{ trans('akaunting-reports::ui.last_contribution') }}" />
                                </x-slot>
                            </x-table.th>
                            <x-table.th override="class" class="lg:w-3/12 min-w-0 px-2 py-3 text-xs font-medium text-black text-left rtl:text-right">
                                <x-slot name="first">
                                    <x-sortablelink column="direct_cents" title="{{ trans('akaunting-reports::ui.direct_contribution') }}" />
                                </x-slot>
                                <x-slot name="second">
                                    <x-sortablelink column="retained_cents" title="{{ trans('akaunting-reports::ui.retained_production') }}" />
                                </x-slot>
                            </x-table.th>
                            <x-table.th override="class" class="lg:w-2/12 min-w-0 px-2 py-3 text-xs font-medium text-black text-left rtl:text-right">
                                <x-slot name="first">
                                    <x-sortablelink column="saldo_cents" title="{{ trans('akaunting-reports::ui.balance') }}" />
                                </x-slot>
                                <x-slot name="second">
                                    <x-sortablelink column="devolvido_cents" title="{{ trans('akaunting-reports::ui.refunds') }}" />
                                </x-slot>
                            </x-table.th>
                            <x-table.th override="class" class="lg:w-4/12 min-w-0 px-2 py-3 text-xs font-medium text-black text-left rtl:text-right">
                                <x-slot name="first">
                                    <x-sortablelink column="pending" title="{{ trans('akaunting-reports::ui.pending_with_warnings') }}" />
                                </x-slot>
                                <x-slot name="second">
                                    {{ trans('akaunting-reports::ui.status_with_warnings') }}
                                </x-slot>
                            </x-table.th>
                        </x-table.tr>
                    </x-table.thead>
                    <x-table.tbody>
                        @forelse ($class->memberPages as $member)
                            <x-table.tr class="relative flex flex-wrap lg:flex-nowrap lg:items-center px-1 group border-b hover:bg-gray-100">
                                <x-table.td override="class" class="w-7/12 lg:w-3/12 min-w-0 px-2 py-3 text-sm text-black">
                                    <x-slot name="first" override="class" class="font-medium whitespace-normal break-words">
                                        <span class="block text-xs font-normal text-gray-500 lg:hidden">{{ trans('akaunting-reports::ui.member') }}</span>
                                        <a href="{{ $class->reportUrl('ledger', $member['contact_id']) }}"
                                           class="text-blue-600 hover:underline">{{ $member['name'] }}</a>
                                    </x-slot>
                                    <x-slot name="second" override="class" class="mt-1 text-xs text-gray-500 whitespace-normal">
                                        <span class="lg:hidden">{{ trans('akaunting-reports::ui.last_contribution_colon') }} </span>
                                        {{ $member['last_contribution_date'] !== null
                                            ? company_date($member['last_contribution_date']) : trans('akaunting-reports::ui.no_contribution') }}
                                    </x-slot>
                                </x-table.td>
                                <x-table.td override="class" class="w-5/12 lg:w-3/12 min-w-0 px-2 py-3 text-sm text-black">
                                    <x-slot name="first" override="class" class="font-medium tabular-nums break-words">
                                        <span class="mr-1 text-xs font-normal text-gray-500 lg:hidden">{{ trans('akaunting-reports::ui.direct_colon') }}</span>
                                        {{ money(($member['direct_cents']) / 100, default_currency(), true) }}
                                    </x-slot>
                                    <x-slot name="second" override="class" class="mt-1 text-xs text-gray-500 tabular-nums break-words">
                                        <span class="lg:hidden">{{ trans('akaunting-reports::ui.retained_colon') }} </span>
                                        {{ money(($member['retained_cents']) / 100, default_currency(), true) }}
                                    </x-slot>
                                </x-table.td>
                                <x-table.td override="class" class="w-5/12 lg:w-2/12 min-w-0 px-2 py-3 text-sm text-black">
                                    <x-slot name="first" override="class" class="font-semibold tabular-nums break-words">
                                        <span class="mr-1 text-xs font-normal text-gray-500 lg:hidden">{{ trans('akaunting-reports::ui.balance_colon') }}</span>
                                        {{ money(($member['saldo_cents']) / 100, default_currency(), true) }}
                                    </x-slot>
                                    <x-slot name="second" override="class" class="mt-1 text-xs text-gray-500 tabular-nums break-words">
                                        <span class="lg:hidden">{{ trans('akaunting-reports::ui.refunds_colon') }} </span>
                                        {{ money(($member['devolvido_cents']) / 100, default_currency(), true) }}
                                    </x-slot>
                                </x-table.td>
                                <x-table.td override="class" class="w-7/12 lg:w-4/12 min-w-0 px-2 py-3 text-sm text-black">
                                    <x-slot name="first" override="class" class="flex flex-wrap items-center gap-2">
                                        @if ($member['pending'] > 0)
                                            <span class="rounded-full bg-yellow-50 px-2 py-1 text-xs font-semibold text-yellow-700"
                                                  title="{{ $member['pending'] }} {{ $member['pending'] === 1 ? trans('akaunting-reports::ui.movement_unconfirmed_singular') : trans('akaunting-reports::ui.movement_unconfirmed_plural') }}"
                                                  aria-label="{{ $member['pending'] }} {{ $member['pending'] === 1 ? trans('akaunting-reports::ui.not_confirmed_singular') : trans('akaunting-reports::ui.not_confirmed_plural') }}">
                                                {{ $member['pending'] }} {{ $member['pending'] === 1 ? trans('akaunting-reports::ui.not_confirmed_singular') : trans('akaunting-reports::ui.not_confirmed_plural') }}
                                            </span>
                                        @endif
                                        @if ($member['warnings'] > 0)
                                            <span class="rounded-full bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-700"
                                                  title="{{ $member['warnings'] }} {{ $member['warnings'] === 1 ? trans('akaunting-reports::ui.reservation_singular') : trans('akaunting-reports::ui.reservation_plural') }}">
                                                {{ $member['warnings'] }} {{ $member['warnings'] === 1 ? 'ressalva' : 'ressalvas' }}
                                            </span>
                                        @endif
                                        @if ($member['pending'] === 0 && $member['warnings'] === 0)
                                            <span class="text-xs text-green-700">{{ trans('akaunting-reports::ui.clean') }}</span>
                                        @endif
                                    </x-slot>
                                    <x-slot name="second" override="class" class="mt-1 whitespace-normal break-words">
                                        @foreach ($member['status_labels'] as $label => $total)
                                            <div class="text-xs leading-5 text-yellow-700" title="{{ $label }}">
                                                {{ \Modules\AkauntingReports\Support\CapitalSocialReview::shortLabel($label) }}
                                                @if ($total > 1)
                                                    <span class="font-semibold" aria-label="{{ $total }} {{ trans('akaunting-reports::ui.status_count') }}">×{{ $total }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </x-slot>
                                </x-table.td>
                            </x-table.tr>
                        @empty
                            <x-table.tr><x-table.td class="w-full">{{ trans('akaunting-reports::ui.no_members') }}</x-table.td></x-table.tr>
                        @endforelse
                    </x-table.tbody>
                </x-table>
            </div>
            @if ($class->memberPages->hasPages())
                <div class="mt-4">{{ $class->memberPages->links() }}</div>
            @endif
        @else
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold text-black-400">{{ trans('akaunting-reports::ui.movement_ledger') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ count($class->movements) }} {{ trans('akaunting-reports::ui.entries_found_sort') }}
                        @if ($class->selectedMember !== null)
                            {{ trans('akaunting-reports::ui.running_balance_explained') }}
                        @else
                            {{ trans('akaunting-reports::ui.running_balance_filter') }}
                        @endif
                    </p>
                </div>
                <span class="text-sm text-gray-500">{{ trans('akaunting-reports::ui.entry_count') }}</span>
            </div>

            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <x-table aria-label="{{ trans('akaunting-reports::ui.capital_ledger') }}">
                    <x-table.thead>
                        <x-table.tr>
                            <x-table.th class="w-2/12"><x-sortablelink column="date" title="{{ trans('akaunting-reports::ui.date') }}" /></x-table.th>
                            @if ($class->selectedMember === null)
                                <x-table.th class="w-2/12"><x-sortablelink column="name" title="{{ trans('akaunting-reports::ui.member') }}" /></x-table.th>
                            @endif
                            <x-table.th class="w-2/12"><x-sortablelink column="kind" title="{{ trans('akaunting-reports::ui.kind') }}" /></x-table.th>
                            <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="amount_cents" title="{{ trans('akaunting-reports::ui.value') }}" /></x-table.th>
                            @if ($class->selectedMember !== null)
                                <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="running_balance_cents" title="{{ trans('akaunting-reports::ui.individual_balance') }}" /></x-table.th>
                            @endif
                            <x-table.th class="w-2/12"><x-sortablelink column="status" title="{{ trans('akaunting-reports::ui.status') }}" /></x-table.th>
                            <x-table.th class="w-2/12"><x-sortablelink column="source" title="{{ trans('akaunting-reports::ui.source') }}" /></x-table.th>
                        </x-table.tr>
                    </x-table.thead>
                    <x-table.tbody>
                        @forelse ($class->movementPages as $row)
                            <x-table.tr>
                                <x-table.td class="w-2/12">{{ company_date($row['date']) }}</x-table.td>
                                @if ($class->selectedMember === null)
                                    <x-table.td class="w-2/12">{{ $row['name'] }}</x-table.td>
                                @endif
                                <x-table.td class="w-2/12">
                                    @switch($row['kind'])
                                        @case('integralizacao') {{ trans('akaunting-reports::ui.direct_contribution') }} @break
                                        @case('retencao') {{ trans('akaunting-reports::ui.retained_production') }} @break
                                        @case('devolucao') {{ trans('akaunting-reports::ui.refund') }} @break
                                        @default {{ trans('akaunting-reports::ui.manual_review') }}
                                    @endswitch
                                </x-table.td>
                                <x-table.td kind="amount" class="w-2/12">
                                    @if ($row['status'] === 'confirmado')
                                        {{ money(($row['amount_cents']) / 100, default_currency(), true) }}
                                    @else
                                        <span class="text-gray-500">{{ trans('akaunting-reports::ui.unconfirmed') }}</span>
                                        @if ($row['candidate_cents'] > 0)
                                            <div class="mt-1 text-xs text-yellow-700">{{ money(($row['candidate_cents']) / 100, default_currency(), true) }} {{ trans('akaunting-reports::ui.pending_in_review') }}</div>
                                        @endif
                                    @endif
                                </x-table.td>
                                @if ($class->selectedMember !== null)
                                    <x-table.td kind="amount" class="w-2/12">{{ money(($row['running_balance_cents']) / 100, default_currency(), true) }}</x-table.td>
                                @endif
                                <x-table.td class="w-2/12">
                                    @php $reviewType = \Modules\AkauntingReports\Support\CapitalSocialReview::category($row); @endphp
                                    <span class="{{ $reviewType === 'confirmed' ? 'text-green-700' : 'text-yellow-700' }} font-medium">
                                        {{ \Modules\AkauntingReports\Support\CapitalSocialReview::label($row) }}
                                    </span>
                                </x-table.td>
                                <x-table.td class="w-2/12">
                                    @php
                                        $sourceRoute = $row['source'] === 'transaction' ? 'transactions.show'
                                            : ($row['document_type'] === 'bill' ? 'bills.show' : 'invoices.show');
                                    @endphp
                                    <a href="{{ route($sourceRoute, $row['id']) }}" class="text-blue-600 hover:underline"
                                       title="{{ trans('akaunting-reports::ui.open_source') }}">
                                        {{ trans('akaunting-reports::ui.source_' . $row['source']) }} #{{ $row['id'] }}
                                    </a>
                                    @if ($row['status'] !== 'confirmado' || $row['reason'] !== '')
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ \Modules\AkauntingReports\Support\CapitalSocialReview::guidance($row) }}
                                        </div>
                                    @endif
                                </x-table.td>
                            </x-table.tr>
                        @empty
                            <x-table.tr><x-table.td class="w-full">{{ trans('akaunting-reports::ui.no_entries') }}</x-table.td></x-table.tr>
                        @endforelse
                    </x-table.tbody>
                </x-table>
            </div>
            @if ($class->movementPages->hasPages())
                <div class="mt-4">{{ $class->movementPages->links() }}</div>
            @endif
        @endif
    @endif
</section>
