{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div id="widget-{{ $class->model->id }}" class="{{ $class->model->settings->width ?? $class->model->getWidthAttribute($class->getDefaultSettings()['width']) }} my-8">
    @include($class->views['header'], ['header_class' => ''])

    <div class="grid grid-cols-3 gap-3 my-3 text-center">
        @foreach (['critical', 'warning', 'info'] as $key)
            <div>
                <div class="text-lg font-medium tabular-nums text-black">{{ $counts[$key] }}</div>
                <div class="text-xs text-black-400">{{ trans('akaunting-reports::audit.' . $key) }}</div>
            </div>
        @endforeach
    </div>
    @if ($periods !== [])
        <div class="mt-4 border-t pt-3 text-xs text-black-400">
            <div class="font-medium text-black mb-2">{{ trans('akaunting-reports::audit.by_period') }}</div>
            @foreach ($periods as $month => $count)
                <div class="flex justify-between py-1">
                    <span>{{ $month === 'unknown' ? trans('akaunting-reports::audit.unknown_period') : $month }}</span>
                    <span class="tabular-nums">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    @endif
    <p class="text-xs text-black-400 mt-3">
        {{ trans('akaunting-reports::audit.scanned') }}: {{ $scanned }}.
        @if ($truncated) {{ trans('akaunting-reports::audit.preview_truncated') }} @endif
    </p>
</div>
