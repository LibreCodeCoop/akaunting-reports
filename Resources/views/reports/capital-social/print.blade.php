{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.print>
    <x-slot name="title">{{ $class->model->name }}</x-slot>
    <x-slot name="content">
        <h2>{{ $class->model->name }}</h2>
        <p>{{ setting('company.name') }} · Posição em {{ company_date($class->asOf) }}</p>
        @if ($class->configurationError)
            <p>{{ $class->configurationError }}</p>
        @else
            <p>
                <strong>Saldo confirmado:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['total_cents']) }} ·
                <strong>Integralização direta:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['direct_cents']) }} ·
                <strong>Retenções:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['retained_cents']) }} ·
                <strong>Devoluções:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['devolvido_cents']) }}
            </p>
            <p>{{ $class->capitalSummary['confirmed_count'] }} movimentações confirmadas,
                {{ $class->capitalSummary['pending_count'] }} pendentes (não contabilizadas).
            </p>

            @if ($class->tab === 'overview')
                <h3>Posição por cooperado</h3>
                <table class="w-full">
                    <thead>
                        <tr><th>Cooperado</th><th>Direto</th><th>Retido</th><th>Devolvido</th><th>Saldo</th><th>Pendências</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($class->sortedMembers as $member)
                            <tr>
                                <td>{{ $member['name'] }}</td>
                                <td>{{ \Modules\AkauntingReports\Support\Money::format($member['direct_cents']) }}</td>
                                <td>{{ \Modules\AkauntingReports\Support\Money::format($member['retained_cents']) }}</td>
                                <td>{{ \Modules\AkauntingReports\Support\Money::format($member['devolvido_cents']) }}</td>
                                <td>{{ \Modules\AkauntingReports\Support\Money::format($member['saldo_cents']) }}</td>
                                <td>{{ $member['pending'] }} não confirmadas; {{ $member['warnings'] }} ressalvas</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <h3>Extrato de movimentações</h3>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th>Data</th><th>Cooperado</th><th>Movimentação</th><th>Valor</th><th>Situação</th><th>Origem</th>
                            @if ($class->selectedMember !== null) <th>Saldo individual</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($class->movements as $row)
                            <tr>
                                <td>{{ company_date($row['date']) }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['kind'] }}</td>
                                <td>
                                    {{ \Modules\AkauntingReports\Support\Money::format($row['amount_cents']) }}
                                    @if ($row['status'] === 'revisar' && $row['candidate_cents'] > 0)
                                        ({{ \Modules\AkauntingReports\Support\Money::format($row['candidate_cents']) }} em análise)
                                    @endif
                                </td>
                                <td>{{ \Modules\AkauntingReports\Support\CapitalSocialReview::label($row) }}</td>
                                <td>{{ $row['source'] === 'document' ? 'Documento' : 'Transação' }} #{{ $row['id'] }}
                                    @if ($row['reason'] !== '') — {{ $row['reason'] }} @endif
                                </td>
                                @if ($class->selectedMember !== null)
                                    <td>{{ \Modules\AkauntingReports\Support\Money::format($row['running_balance_cents']) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </x-slot>
</x-layouts.print>
