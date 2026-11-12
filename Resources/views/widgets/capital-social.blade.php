{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<section class="rounded-lg bg-white p-4" aria-label="{{ trans('akaunting-reports::ui.capital_widget_title') }}">
    @if (! $configured)
        <p class="text-sm text-gray-500">{{ trans('akaunting-reports::ui.capital_widget_unconfigured') }}</p>
    @else
        <p class="text-xs text-gray-500">{{ trans('akaunting-reports::ui.capital_widget_confirmed') }}</p>
        <p class="mt-2 text-2xl font-semibold tabular-nums text-black-400">
            {{ money($metrics['total_cents'] / 100, default_currency(), true) }}
        </p>
        <p class="mt-1 text-xs text-gray-500">{{ trans('akaunting-reports::ui.as_of') }} {{ company_date($asOf) }}</p>
        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-200 pt-3">
            <div>
                <span class="block text-lg font-semibold tabular-nums">{{ $metrics['pending_count'] }}</span>
                <span class="text-xs text-gray-500">{{ trans('akaunting-reports::ui.capital_widget_pending') }}</span>
            </div>
            <div>
                <span class="block text-lg font-semibold tabular-nums">{{ $metrics['warnings_count'] }}</span>
                <span class="text-xs text-gray-500">{{ trans('akaunting-reports::ui.capital_widget_warnings') }}</span>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-600">{{ trans('akaunting-reports::ui.capital_widget_equity_note') }}</p>
        @if ($url = $class->getReportUrl())
            <a href="{{ $url }}" class="mt-3 inline-block text-sm text-blue-600 hover:underline">
                {{ trans('akaunting-reports::ui.capital_widget_open_report') }}
            </a>
        @endif
    @endif
</section>
