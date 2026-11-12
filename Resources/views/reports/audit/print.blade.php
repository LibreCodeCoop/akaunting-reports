{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.print>
    <x-slot name="title">{{ trans('akaunting-reports::audit.title') }}</x-slot>
    <x-slot name="content">
        <h2>{{ trans('akaunting-reports::audit.title') }}</h2>
        <p>{{ trans('akaunting-reports::audit.interpretation') }}</p>
        @if ($class->truncated)
            <p>{{ trans('akaunting-reports::audit.truncated') }}</p>
        @endif
        <p>{{ trans('akaunting-reports::audit.scanned') }}: {{ $class->scanned }} ·
            {{ trans('akaunting-reports::audit.total') }}: {{ $class->counts['total'] }}</p>
        <table class="w-full">
            <thead><tr>
                @foreach (['severity', 'classification', 'rule', 'source', 'competence', 'guidance'] as $key)
                    <th>{{ trans('akaunting-reports::audit.' . $key) }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach ($class->filtered as $finding)
                    <tr>
                        <td>{{ trans('akaunting-reports::audit.' . $finding->severity) }}</td>
                        <td>{{ trans('akaunting-reports::audit.' . $finding->classification) }}</td>
                        <td>{{ trans('akaunting-reports::audit.' . $finding->caseKey) }}</td>
                        <td>{{ trans('akaunting-reports::audit.' . $finding->source) }} #{{ $finding->sourceId }}</td>
                        <td>{{ $finding->competence ?? '—' }}</td>
                        <td>{{ trans('akaunting-reports::audit.' . substr($finding->guidanceKey, 6)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot>
</x-layouts.print>
