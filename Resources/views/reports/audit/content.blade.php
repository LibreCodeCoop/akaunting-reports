{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Included inside Akaunting's native report layout, filter bar and toolbar. --}}
<section class="my-6 space-y-5">
    <div class="flex flex-wrap justify-between items-center gap-2">
        <div>
            <h2 class="text-xl font-semibold text-black-400">{{ trans('akaunting-reports::audit.title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ trans('akaunting-reports::audit.description') }}</p>
        </div>
        <span class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.scanned') }}: {{ $class->scanned }}</span>
    </div>
    @if ($class->truncated)
        <p role="alert" class="rounded-lg border border-yellow-300 p-3 text-yellow-800">
            {{ trans('akaunting-reports::audit.truncated') }}
        </p>
    @endif
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach (['total', 'confirmed', 'suspected', 'insufficient'] as $key)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.' . $key) }}</div>
                <div class="text-xl font-semibold tabular-nums text-black-400">{{ $class->counts[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-600">{{ trans('akaunting-reports::audit.interpretation') }}</p>
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs lg:hidden"
         aria-label="{{ trans('akaunting-reports::audit.sort') }}">
        <span class="text-gray-500">{{ trans('akaunting-reports::audit.sort') }}</span>
        @foreach (['severity', 'classification', 'rule', 'source', 'competence', 'guidance'] as $column)
            <x-sortablelink column="{{ $column }}" title="{{ trans('akaunting-reports::audit.' . $column) }}" />
        @endforeach
    </div>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <x-table aria-label="{{ trans('akaunting-reports::audit.table_title') }}" style="width: 100%;">
            <x-table.thead class="hidden lg:block">
                <x-table.tr>
                    @foreach (['severity', 'classification', 'rule', 'source', 'competence', 'guidance'] as $column)
                        <x-table.th override="class" class="{{ $column === 'rule' ? 'lg:w-3/12' : ($column === 'guidance' ? 'lg:w-3/12' : 'lg:w-1/12') }} min-w-0 px-2 py-3 text-xs font-semibold text-black text-left">
                            <x-sortablelink column="{{ $column }}" title="{{ trans('akaunting-reports::audit.' . $column) }}" />
                        </x-table.th>
                    @endforeach
                </x-table.tr>
            </x-table.thead>
            <x-table.tbody>
                @forelse ($class->visible as $finding)
                    <x-table.tr class="flex flex-wrap lg:flex-nowrap lg:items-start border-b px-1 hover:bg-gray-50">
                        <x-table.td override="class" class="w-1/2 lg:w-1/12 min-w-0 px-2 py-3 text-sm text-black">
                            <span class="block text-xs text-gray-500 lg:hidden">{{ trans('akaunting-reports::audit.severity') }}</span>
                            {{ trans('akaunting-reports::audit.' . $finding->severity) }}
                        </x-table.td>
                        <x-table.td override="class" class="w-1/2 lg:w-1/12 min-w-0 px-2 py-3 text-sm text-black">
                            <span class="block text-xs text-gray-500 lg:hidden">{{ trans('akaunting-reports::audit.classification') }}</span>
                            {{ trans('akaunting-reports::audit.' . $finding->classification) }}
                        </x-table.td>
                        <x-table.td override="class" class="w-full lg:w-3/12 min-w-0 px-2 py-3 text-sm text-black">
                            <span class="block text-xs text-gray-500 lg:hidden">{{ trans('akaunting-reports::audit.rule') }}</span>
                            <span class="break-words">{{ trans('akaunting-reports::audit.' . $finding->caseKey) }}</span>
                            <div class="mt-1 text-xs text-gray-500">{{ $finding->ruleId }} v{{ $finding->ruleVersion }}</div>
                        </x-table.td>
                        <x-table.td override="class" class="w-1/2 lg:w-2/12 min-w-0 px-2 py-3 text-sm text-black">
                            <span class="block text-xs text-gray-500 lg:hidden">{{ trans('akaunting-reports::audit.source') }}</span>
                            @if ($url = $class->sourceUrl($finding))
                                <a href="{{ $url }}" class="text-blue-600 hover:underline" title="{{ trans('akaunting-reports::audit.open_source') }}">
                                    {{ trans('akaunting-reports::audit.' . $finding->source) }} #{{ $finding->sourceId }}
                                </a>
                            @else
                                {{ trans('akaunting-reports::audit.' . $finding->source) }} #{{ $finding->sourceId }}
                            @endif
                        </x-table.td>
                        <x-table.td override="class" class="w-1/2 lg:w-1/12 min-w-0 px-2 py-3 text-sm text-black tabular-nums whitespace-nowrap">
                            <span class="block text-xs text-gray-500 lg:hidden">{{ trans('akaunting-reports::audit.competence') }}</span>
                            {{ $finding->competence ?? '—' }}
                        </x-table.td>
                        <x-table.td override="class" class="w-full lg:w-3/12 min-w-0 px-2 py-3 text-sm text-black">
                            <span class="block text-xs text-gray-500 lg:hidden">{{ trans('akaunting-reports::audit.guidance') }}</span>
                            <span class="break-words">{{ trans('akaunting-reports::audit.' . substr($finding->guidanceKey, 6)) }}</span>
                        </x-table.td>
                    </x-table.tr>
                @empty
                    <x-table.tr><x-table.td class="w-full">{{ trans('akaunting-reports::audit.no_findings') }}</x-table.td></x-table.tr>
                @endforelse
            </x-table.tbody>
        </x-table>
    </div>
    <div class="flex items-center justify-between gap-3 text-xs text-gray-600">
        <span>{{ trans('akaunting-reports::audit.matching') }}: {{ $class->matching }} ·
            {{ trans('akaunting-reports::audit.page') }} {{ $class->page }} / {{ $class->pages }}</span>
        <nav aria-label="{{ trans('akaunting-reports::audit.page_navigation') }}" class="flex gap-4">
            @if ($class->page > 1)
                <a class="text-blue-600 hover:underline" href="{{ request()->fullUrlWithQuery(['page' => $class->page - 1]) }}">
                    {{ trans('akaunting-reports::audit.previous') }}
                </a>
            @endif
            @if ($class->page < $class->pages)
                <a class="text-blue-600 hover:underline" href="{{ request()->fullUrlWithQuery(['page' => $class->page + 1]) }}">
                    {{ trans('akaunting-reports::audit.next') }}
                </a>
            @endif
        </nav>
    </div>
    <p class="text-xs text-gray-500">{{ trans('akaunting-reports::audit.read_only') }}</p>
</section>
