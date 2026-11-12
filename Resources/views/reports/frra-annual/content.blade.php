{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div class="space-y-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div>
            <h3 class="text-xl font-semibold text-black">{{ trans('akaunting-reports::ui.frra_annual') }} · {{ $class->selectedYear }}</h3>
            <p class="mt-1 text-sm text-gray-500">
                {{ trans('akaunting-reports::ui.frra_intro_first') }}
                {{ trans('akaunting-reports::ui.total_no_pay') }}
            </p>
        </div>
        <span class="text-xs text-gray-500">{{ $class->summary['members_count'] }} {{ trans('akaunting-reports::ui.members_count') }}</span>
    </div>

    @if ($class->configurationError !== null)
        <p class="rounded-lg border border-yellow-200 p-4 text-yellow-700">{{ $class->configurationError }}</p>
    @else
        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2 border-b border-gray-200 pb-4">
            <div>
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::ui.frra_registered') }}</div>
                <div class="text-xl font-semibold text-black tabular-nums">
                    {{ money(($class->summary['total_cents']) / 100, default_currency(), true) }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::ui.retained_production_plural') }}</div>
                <div class="text-base font-semibold text-black tabular-nums">
                    {{ money(($class->summary['production_cents']) / 100, default_currency(), true) }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::ui.annual_bill_supplement') }}</div>
                <div class="text-base font-semibold text-black tabular-nums">
                    {{ money(($class->summary['annual_cents']) / 100, default_currency(), true) }}
                </div>
            </div>
        </div>

        @if (($class->issues['unallocated_by_year'][$class->selectedYear]['items'] ?? 0) > 0)
            <p class="rounded-lg border border-yellow-200 p-3 text-xs text-yellow-800">
                {{ trans('akaunting-reports::ui.frra_history_incomplete') }}
                {{ $class->issues['unallocated_by_year'][$class->selectedYear]['items'] }} {{ trans('akaunting-reports::ui.frra_history_part') }}
                {{ trans('akaunting-reports::ui.frra_ambiguous_competences') }}
                {{ money(($class->issues['unallocated_by_year'][$class->selectedYear]['cents']) / 100, default_currency(), true) }}.
                {{ trans('akaunting-reports::ui.frra_unallocated_help') }}
            </p>
        @endif

        @if ($class->summary['annual_only_months'] > 0 || $class->summary['draft_items'] > 0)
            <p class="text-xs text-gray-600">
                {{ $class->summary['annual_only_months'] }} {{ trans('akaunting-reports::ui.frra_annual_count') }}
                {{ $class->summary['draft_items'] }} {{ trans('akaunting-reports::ui.frra_draft_count') }}
                {{ trans('akaunting-reports::ui.frra_no_duplicate_help') }}
                <strong>†</strong> {{ trans('akaunting-reports::ui.frra_annual_only_help') }}
            </p>
        @endif
        @if ($class->summary['annual_overlap_months'] > 0
            || $class->summary['duplicate_annual_months'] > 0
            || $class->issues['currency_mismatch_count'] > 0
            || $class->issues['invalid_amount_count'] > 0
            || $class->issues['excluded_status_count'] > 0)
            <p class="rounded-lg border border-yellow-200 p-3 text-xs text-yellow-800">
                {{ trans('akaunting-reports::ui.frra_audit_attention') }}
                {{ $class->summary['annual_overlap_months'] }} {{ trans('akaunting-reports::ui.frra_overlap_summary') }}
                {{ $class->summary['duplicate_annual_months'] }} {{ trans('akaunting-reports::ui.frra_duplicate_summary') }}
                {{ $class->issues['currency_mismatch_count'] }} {{ trans('akaunting-reports::ui.frra_history_currency') }};
                {{ $class->issues['excluded_status_count'] }} {{ trans('akaunting-reports::ui.frra_invalid_status') }};
                {{ $class->issues['invalid_amount_count'] }} {{ trans('akaunting-reports::ui.frra_invalid_amount') }}.
                @if ($class->summary['overlap_difference_cents'] !== 0)
                    {{ trans('akaunting-reports::ui.frra_disagreement') }}
                    {{ money(($class->summary['overlap_difference_cents']) / 100, default_currency(), true) }}.
                @endif
            </p>
        @endif

        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs" aria-label="{{ trans('akaunting-reports::ui.sort_frra') }}">
            <span class="text-gray-500">{{ trans('akaunting-reports::ui.sort') }}</span>
            <x-sortablelink column="name" title="{{ trans('akaunting-reports::ui.member') }}" />
            <x-sortablelink column="total_cents" title="{{ trans('akaunting-reports::ui.total_annual') }}" />
            <x-sortablelink column="production_cents" title="{{ trans('akaunting-reports::ui.short_retained_production') }}" />
            <x-sortablelink column="annual_cents" title="{{ trans('akaunting-reports::ui.annual_supplement') }}" />
        </div>
        <div class="rounded-lg border border-gray-200 bg-white">
            <x-table aria-label="{{ trans('akaunting-reports::ui.frra_table') }}" style="width: 100%;">
                <x-table.thead class="hidden lg:block">
                    <x-table.tr>
                        <x-table.th override="class" class="lg:w-3/12 min-w-0 px-2 py-3 text-xs text-left font-semibold text-black">{{ trans('akaunting-reports::ui.member') }}</x-table.th>
                        @foreach (range(0, 3) as $quarterTitle)
                            <x-table.th override="class" class="lg:w-2/12 min-w-0 px-2 py-3 text-xs text-left font-semibold text-black">{{ trans('akaunting-reports::ui.months.' . ($quarterTitle * 3 + 1)) }} – {{ trans('akaunting-reports::ui.months.' . ($quarterTitle * 3 + 3)) }}</x-table.th>
                        @endforeach
                        <x-table.th override="class" class="lg:w-1/12 min-w-0 px-2 py-3 text-xs text-left font-semibold text-black">{{ trans('akaunting-reports::ui.total') }}</x-table.th>
                    </x-table.tr>
                </x-table.thead>
                <x-table.tbody>
                    @forelse ($class->summary['members'] as $member)
                        <x-table.tr class="flex flex-wrap lg:flex-nowrap lg:items-start border-b px-1 hover:bg-gray-50">
                            <x-table.td override="class" class="w-full lg:w-3/12 min-w-0 px-2 py-3 text-sm text-black">
                                <div class="font-medium whitespace-normal break-words">{{ $member['name'] }}</div>
                                @if ($member['annual_cents'] > 0)
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ trans('akaunting-reports::ui.annual_supplement_colon') }}
                                        {{ money(($member['annual_cents']) / 100, default_currency(), true) }}
                                    </div>
                                @endif
                            </x-table.td>
                            @foreach (range(0, 3) as $quarter)
                                <x-table.td override="class" class="w-6/12 lg:w-2/12 min-w-0 px-2 py-3 text-sm text-black">
                                    <div class="mb-1 text-xs font-semibold text-gray-500 lg:hidden">
                                        {{ trans('akaunting-reports::ui.months.' . ($quarter * 3 + 1)) }} – {{ trans('akaunting-reports::ui.months.' . ($quarter * 3 + 3)) }}
                                    </div>
                                    @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                        <div class="flex justify-between gap-2 text-xs leading-6 tabular-nums">
                                            <span class="text-gray-500">{{ trans('akaunting-reports::ui.months.' . $month) }}</span>
                                            <span class="whitespace-nowrap">
                                                @if ($member['counts'][$month] === 0)
                                                    <span class="text-gray-400">—</span>
                                                @else
                                                    {{ money(($member['months'][$month]) / 100, default_currency(), true) }}
                                                    @if ($member['sources'][$month] === 'annual')
                                                        <strong class="text-yellow-700" title="{{ trans('akaunting-reports::ui.frra_supplement_from_annual') }}">†</strong>
                                                    @endif
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </x-table.td>
                            @endforeach
                            <x-table.td override="class" class="w-full lg:w-1/12 min-w-0 px-2 py-3 text-sm text-black">
                                <span class="text-xs font-semibold text-gray-500 lg:hidden">{{ trans('akaunting-reports::ui.total_annual_colon') }} </span>
                                <span class="font-semibold tabular-nums">{{ money(($member['total_cents']) / 100, default_currency(), true) }}</span>
                            </x-table.td>
                        </x-table.tr>
                    @empty
                        <x-table.tr><x-table.td class="w-full">{{ trans('akaunting-reports::ui.no_frra') }}</x-table.td></x-table.tr>
                    @endforelse
                    <x-table.tr class="flex flex-wrap lg:flex-nowrap lg:items-start bg-gray-50 border-t border-gray-300">
                        <x-table.td override="class" class="w-full lg:w-3/12 min-w-0 px-2 py-3 font-semibold text-black">{{ trans('akaunting-reports::ui.total') }} {{ $class->selectedYear }}</x-table.td>
                        @foreach (range(0, 3) as $quarter)
                            <x-table.td override="class" class="w-6/12 lg:w-2/12 min-w-0 px-2 py-3 text-sm text-black">
                                <div class="mb-1 text-xs font-semibold text-gray-500 lg:hidden">
                                    {{ trans('akaunting-reports::ui.months.' . ($quarter * 3 + 1)) }} – {{ trans('akaunting-reports::ui.months.' . ($quarter * 3 + 3)) }}
                                </div>
                                @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                    <div class="flex justify-between gap-2 text-xs leading-6 tabular-nums font-semibold">
                                        <span class="text-gray-500">{{ trans('akaunting-reports::ui.months.' . $month) }}</span>
                                        <span class="whitespace-nowrap">{{ money(($class->summary['months'][$month]) / 100, default_currency(), true) }}</span>
                                    </div>
                                @endforeach
                            </x-table.td>
                        @endforeach
                        <x-table.td override="class" class="w-full lg:w-1/12 min-w-0 px-2 py-3 font-bold tabular-nums text-black">
                            <span class="text-xs lg:hidden">{{ trans('akaunting-reports::ui.total_annual_colon') }} </span>
                            {{ money(($class->summary['total_cents']) / 100, default_currency(), true) }}
                        </x-table.td>
                    </x-table.tr>
                </x-table.tbody>
            </x-table>
        </div>
        <p class="text-xs text-gray-500">
            @if ($class->issues['undated_count'] > 0)
                {{ trans('akaunting-reports::ui.frra_year_counts') }} {{ $class->issues['undated_count'] }} {{ trans('akaunting-reports::ui.frra_undated_global') }}
            @endif
            {{ trans('akaunting-reports::ui.frra_source_help') }}
        </p>
    @endif
</div>
