{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div class="bg-white p-4 rounded-lg">
    <div class="grid grid-cols-3 gap-3 text-center">
        @foreach (['critical', 'warning', 'info'] as $key)
            <div>
                <div class="text-lg font-semibold tabular-nums">{{ $counts[$key] }}</div>
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.' . $key) }}</div>
            </div>
        @endforeach
    </div>
    @if ($periods !== [])
        <div class="mt-4 border-t border-gray-200 pt-3 text-xs">
            <div class="font-medium text-gray-700 mb-2">{{ trans('akaunting-reports::audit.by_period') }}</div>
            @foreach ($periods as $month => $count)
                <div class="flex justify-between py-1">
                    <span>{{ $month === 'unknown' ? trans('akaunting-reports::audit.unknown_period') : $month }}</span>
                    <span class="tabular-nums">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    @endif
    <p class="text-xs text-gray-500 mt-3">
        {{ trans('akaunting-reports::audit.scanned') }}: {{ $scanned }}.
        @if ($truncated) {{ trans('akaunting-reports::audit.preview_truncated') }} @endif
    </p>
</div>
