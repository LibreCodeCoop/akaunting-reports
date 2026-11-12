{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div id="widget-{{ $class->model->id }}" class="{{ $class->model->settings->width ?? $class->model->getWidthAttribute($class->getDefaultSettings()['width']) }} my-8">
    @include($class->views['header'], ['header_class' => ''])

    @if (! $configured)
        <p class="my-3 text-sm text-black-400">{{ trans('akaunting-reports::ui.capital_widget_unconfigured') }}</p>
    @else
        <p class="my-3 text-sm text-black-400">{{ trans('akaunting-reports::ui.capital_widget_confirmed') }}</p>
        <p class="text-2xl font-medium tabular-nums text-black">
            {{ money($metrics['total_cents'] / 100, default_currency(), true) }}
        </p>
        <p class="mt-1 text-xs text-black-400">{{ trans('akaunting-reports::ui.as_of') }} {{ company_date($asOf) }}</p>
        <div class="mt-4 grid grid-cols-2 gap-3 border-t pt-3">
            <div>
                <span class="block text-lg font-medium tabular-nums text-black">{{ $metrics['pending_count'] }}</span>
                <span class="text-xs text-black-400">{{ trans('akaunting-reports::ui.capital_widget_pending') }}</span>
            </div>
            <div>
                <span class="block text-lg font-medium tabular-nums text-black">{{ $metrics['warnings_count'] }}</span>
                <span class="text-xs text-black-400">{{ trans('akaunting-reports::ui.capital_widget_warnings') }}</span>
            </div>
        </div>
        <p class="mt-3 text-xs text-black-400">{{ trans('akaunting-reports::ui.capital_widget_equity_note') }}</p>
    @endif
</div>
