{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div class="space-y-5">
    <div class="flex flex-wrap justify-between items-center gap-2">
        <div>
            <h3 class="text-xl font-semibold text-black">{{ trans('akaunting-reports::audit.title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ trans('akaunting-reports::audit.description') }}</p>
        </div>
        <span class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.scanned') }}: {{ $class->scanned }}</span>
    </div>
    @if ($class->truncated)
        <p role="alert" class="border border-yellow-300 p-3 text-yellow-800">
            {{ trans('akaunting-reports::audit.truncated') }}
        </p>
    @endif
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach (['total', 'confirmed', 'suspected', 'insufficient'] as $key)
            <div class="rounded-lg border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.' . $key) }}</div>
                <div class="text-xl font-semibold tabular-nums">{{ $class->counts[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-600">{{ trans('akaunting-reports::audit.interpretation') }}</p>
    <div class="rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm" aria-label="{{ trans('akaunting-reports::audit.table_title') }}">
            <thead>
                <tr class="bg-gray-50 text-left">
                    @foreach (['severity', 'classification', 'rule', 'source', 'competence', 'guidance'] as $key)
                        <th class="p-3">{{ trans('akaunting-reports::audit.' . $key) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($class->visible as $finding)
                    <tr class="border-t border-gray-200">
                        <td class="p-3">{{ trans('akaunting-reports::audit.' . $finding->severity) }}</td>
                        <td class="p-3">{{ trans('akaunting-reports::audit.' . $finding->classification) }}</td>
                        <td class="p-3">
                            {{ trans('akaunting-reports::audit.' . $finding->caseKey) }}
                            <div class="text-xs text-gray-500">{{ $finding->ruleId }} v{{ $finding->ruleVersion }}</div>
                        </td>
                        <td class="p-3">
                            @if ($url = $class->sourceUrl($finding))
                                <a href="{{ $url }}" class="text-purple hover:underline">{{ trans('akaunting-reports::audit.' . $finding->source) }} #{{ $finding->sourceId }}</a>
                            @else
                                {{ trans('akaunting-reports::audit.' . $finding->source) }} #{{ $finding->sourceId }}
                            @endif
                        </td>
                        <td class="p-3">{{ $finding->competence ?? '—' }}</td>
                        <td class="p-3">
                            {{ trans('akaunting-reports::audit.' . substr($finding->guidanceKey, 6)) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-3">{{ trans('akaunting-reports::audit.no_findings') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between gap-3 text-xs text-gray-600">
        <span>{{ trans('akaunting-reports::audit.matching') }}: {{ $class->matching }} ·
            {{ trans('akaunting-reports::audit.page') }} {{ $class->page }} / {{ $class->pages }}</span>
        <nav aria-label="{{ trans('akaunting-reports::audit.page_navigation') }}" class="flex gap-4">
            @if ($class->page > 1)
                <a class="text-purple hover:underline" href="{{ request()->fullUrlWithQuery(['page' => $class->page - 1]) }}">
                    {{ trans('akaunting-reports::audit.previous') }}
                </a>
            @endif
            @if ($class->page < $class->pages)
                <a class="text-purple hover:underline" href="{{ request()->fullUrlWithQuery(['page' => $class->page + 1]) }}">
                    {{ trans('akaunting-reports::audit.next') }}
                </a>
            @endif
        </nav>
    </div>
    <p class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.read_only') }}</p>
</div>
