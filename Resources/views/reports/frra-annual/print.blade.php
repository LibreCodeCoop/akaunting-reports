{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.print>
    <x-slot name="title">{{ trans('akaunting-reports::ui.frra_annual') }} · {{ $class->selectedYear }}</x-slot>
    <x-slot name="content">
        <h2>{{ trans('akaunting-reports::ui.frra_annual') }} · {{ $class->selectedYear }}</h2>
        <p>{{ trans('akaunting-reports::ui.frra_print_intro') }}</p>
        @if ($class->configurationError)
            <p>{{ $class->configurationError }}</p>
        @else
            <p>
                <strong>{{ trans('akaunting-reports::ui.total_registered') }}</strong>
                {{ money(($class->summary['total_cents']) / 100, default_currency(), true) }} ·
                <strong>{{ trans('akaunting-reports::ui.retained_production_colon') }}</strong>
                {{ money(($class->summary['production_cents']) / 100, default_currency(), true) }} ·
                <strong>{{ trans('akaunting-reports::ui.annual_supplement_colon') }}</strong>
                {{ money(($class->summary['annual_cents']) / 100, default_currency(), true) }}
            </p>
            @if (($class->issues['unallocated_by_year'][$class->selectedYear]['items'] ?? 0) > 0)
                <p><strong>{{ trans('akaunting-reports::ui.year_incomplete_print') }}</strong>
                    {{ $class->issues['unallocated_by_year'][$class->selectedYear]['items'] }} {{ trans('akaunting-reports::ui.items') }}
                    {{ trans('akaunting-reports::ui.frra_unallocated_print') }}
                    {{ money(($class->issues['unallocated_by_year'][$class->selectedYear]['cents']) / 100, default_currency(), true) }},
                    {{ trans('akaunting-reports::ui.frra_unallocated_tail') }}</p>
            @endif
            <table class="w-full">
                <thead>
                    <tr>
                        <th>{{ trans('akaunting-reports::ui.member') }}</th>
                        <th>{{ trans('akaunting-reports::ui.months.1') }} – {{ trans('akaunting-reports::ui.months.3') }}</th><th>{{ trans('akaunting-reports::ui.months.4') }} – {{ trans('akaunting-reports::ui.months.6') }}</th><th>{{ trans('akaunting-reports::ui.months.7') }} – {{ trans('akaunting-reports::ui.months.9') }}</th><th>{{ trans('akaunting-reports::ui.months.10') }} – {{ trans('akaunting-reports::ui.months.12') }}</th>
                        <th>{{ trans('akaunting-reports::ui.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($class->summary['members'] as $member)
                        <tr>
                            <td>{{ $member['name'] }}</td>
                            @foreach (range(0, 3) as $quarter)
                                <td>
                                    @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                        <div>{{ trans('akaunting-reports::ui.months.' . $month) }}:
                                            @if ($member['counts'][$month] > 0)
                                                {{ money(($member['months'][$month]) / 100, default_currency(), true) }}
                                                @if ($member['sources'][$month] === 'annual') † @endif
                                            @else
                                                —
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                            <td>{{ money(($member['total_cents']) / 100, default_currency(), true) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>{{ trans('akaunting-reports::ui.total') }} {{ $class->selectedYear }}</th>
                        @foreach (range(0, 3) as $quarter)
                            <th>
                                @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                    <div>{{ trans('akaunting-reports::ui.months.' . $month) }}:
                                        {{ money(($class->summary['months'][$month]) / 100, default_currency(), true) }}</div>
                                @endforeach
                            </th>
                        @endforeach
                        <th>{{ money(($class->summary['total_cents']) / 100, default_currency(), true) }}</th>
                    </tr>
                </tbody>
            </table>
            <p>{{ trans('akaunting-reports::ui.frra_print_footer_1') }}
                {{ trans('akaunting-reports::ui.frra_print_footer_2') }}</p>
        @endif
    </x-slot>
</x-layouts.print>
