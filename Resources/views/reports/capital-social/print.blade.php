{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.print>
    <x-slot name="title">{{ $class->model->name }}</x-slot>
    <x-slot name="content">
        <h2>{{ $class->model->name }}</h2>
        <p>{{ setting('company.name') }} · {{ trans('akaunting-reports::ui.as_of_heading') }} {{ company_date($class->asOf) }}</p>
        @if ($class->configurationError)
            <p>{{ $class->configurationError }}</p>
        @else
            <p>
                <strong>{{ trans('akaunting-reports::ui.confirmed_balance_colon') }}</strong>
                {{ money(($class->capitalSummary['total_cents']) / 100, default_currency(), true) }} ·
                <strong>{{ trans('akaunting-reports::ui.direct_contribution_colon') }}</strong>
                {{ money(($class->capitalSummary['direct_cents']) / 100, default_currency(), true) }} ·
                <strong>{{ trans('akaunting-reports::ui.withholdings_colon') }}</strong>
                {{ money(($class->capitalSummary['retained_cents']) / 100, default_currency(), true) }} ·
                <strong>{{ trans('akaunting-reports::ui.refunds_colon') }}</strong>
                {{ money(($class->capitalSummary['devolvido_cents']) / 100, default_currency(), true) }}
            </p>
            <p>{{ $class->capitalSummary['confirmed_count'] }} {{ trans('akaunting-reports::ui.confirmed_movements') }},
                {{ $class->capitalSummary['pending_count'] }} {{ trans('akaunting-reports::ui.pending_not_accounted') }}
            </p>

            @if ($class->tab === 'overview')
                <h3>{{ trans('akaunting-reports::ui.position_by_member') }}</h3>
                <table class="w-full">
                    <thead>
                        <tr><th>{{ trans('akaunting-reports::ui.member') }}</th><th>{{ trans('akaunting-reports::ui.direct_short') }}</th><th>{{ trans('akaunting-reports::ui.retained') }}</th><th>{{ trans('akaunting-reports::ui.refund_returned') }}</th><th>{{ trans('akaunting-reports::ui.balance') }}</th><th>{{ trans('akaunting-reports::ui.last_contribution') }}</th><th>{{ trans('akaunting-reports::ui.pending') }}</th><th>{{ trans('akaunting-reports::ui.status') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($class->sortedMembers as $member)
                            <tr>
                                <td>{{ $member['name'] }}</td>
                                <td>{{ money(($member['direct_cents']) / 100, default_currency(), true) }}</td>
                                <td>{{ money(($member['retained_cents']) / 100, default_currency(), true) }}</td>
                                <td>{{ money(($member['devolvido_cents']) / 100, default_currency(), true) }}</td>
                                <td>{{ money(($member['saldo_cents']) / 100, default_currency(), true) }}</td>
                                <td>{{ $member['last_contribution_date'] !== null ? company_date($member['last_contribution_date']) : '—' }}</td>
                                <td>{{ $member['pending'] }} {{ trans('akaunting-reports::ui.not_confirmed_plural') }}; {{ $member['warnings'] }} {{ trans('akaunting-reports::ui.reservation_count') }}</td>
                                <td>
                                    @forelse ($member['status_labels'] as $label => $total)
                                        {{ $label }} ({{ $total }})@if (!$loop->last); @endif
                                    @empty
                                        {{ trans('akaunting-reports::ui.no_occurrences') }}
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <h3>{{ trans('akaunting-reports::ui.movement_ledger') }}</h3>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th>{{ trans('akaunting-reports::ui.date') }}</th><th>{{ trans('akaunting-reports::ui.member') }}</th><th>{{ trans('akaunting-reports::ui.movement') }}</th><th>{{ trans('akaunting-reports::ui.value') }}</th><th>{{ trans('akaunting-reports::ui.status') }}</th><th>{{ trans('akaunting-reports::ui.source') }}</th>
                            @if ($class->selectedMember !== null) <th>{{ trans('akaunting-reports::ui.individual_balance') }}</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($class->movements as $row)
                            <tr>
                                <td>{{ company_date($row['date']) }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ trans('akaunting-reports::ui.kind_' . $row['kind']) }}</td>
                                <td>
                                    {{ money(($row['amount_cents']) / 100, default_currency(), true) }}
                                    @if ($row['status'] === 'revisar' && $row['candidate_cents'] > 0)
                                        ({{ money(($row['candidate_cents']) / 100, default_currency(), true) }} {{ trans('akaunting-reports::ui.pending_in_review') }})
                                    @endif
                                </td>
                                <td>{{ \Modules\AkauntingReports\Support\CapitalSocialReview::label($row) }}</td>
                                <td>{{ trans('akaunting-reports::ui.source_' . $row['source']) }} #{{ $row['id'] }}
                                    @if ($row['reason'] !== '') — {{ \Modules\AkauntingReports\Support\CapitalSocialReview::reason((string) $row['reason']) }} @endif
                                </td>
                                @if ($class->selectedMember !== null)
                                    <td>{{ money(($row['running_balance_cents']) / 100, default_currency(), true) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </x-slot>
</x-layouts.print>
