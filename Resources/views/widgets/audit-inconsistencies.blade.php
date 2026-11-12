{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div class="bg-white p-4 rounded-lg">
    <div class="grid grid-cols-3 gap-3 text-center">
        @foreach (['confirmed', 'suspected', 'insufficient'] as $key)
            <div>
                <div class="text-lg font-semibold tabular-nums">{{ $counts[$key] }}</div>
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.' . $key) }}</div>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500 mt-3">
        {{ trans('akaunting-reports::audit.scanned') }}: {{ $scanned }}.
        @if ($truncated) {{ trans('akaunting-reports::audit.preview_truncated') }} @endif
    </p>
</div>
