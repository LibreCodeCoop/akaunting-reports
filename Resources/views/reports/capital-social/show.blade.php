{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.admin>
    <x-slot name="title">{{ $class->model->name }}</x-slot>
    <x-slot name="content">
        <div class="my-6">
            <p>Capital social confirmado com base em documentos e pagamentos. Valores em conferência não compõem o saldo.</p>
            @if ($class->configurationError)
                <p role="alert">{{ $class->configurationError }}</p>
            @else
                <form method="get" action="{{ route('reports.show', $class->model->id) }}" class="mb-6">
                    <label for="as-of">Data de corte</label>
                    <input id="as-of" type="date" name="as_of" value="{{ $class->asOf }}" />
                    <label for="member-id">Identificador do cooperado (opcional)</label>
                    <input id="member-id" type="number" min="1" name="member_id" value="{{ $class->selectedMember }}" />
                    <button type="submit">Aplicar filtros</button>
                </form>
                <p><strong>Saldo confirmado:</strong> R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['total_cents']) }}</p>
                <p><strong>Pendências:</strong> {{ $class->capitalSummary['pending'] }}</p>
                <h2>Resumo por cooperado</h2>
                <table class="w-full" aria-label="Resumo de capital social">
                    <thead><tr><th>Cooperado</th><th>Integralizado</th><th>Devolvido</th><th>Saldo</th><th>Pendências</th></tr></thead>
                    <tbody>
                    @forelse ($class->capitalSummary['members'] as $member)
                        <tr>
                            <td>{{ $member['name'] }}</td>
                            <td>R$ {{ \Modules\AkauntingReports\Support\Money::format($member['integralizado_cents']) }}</td>
                            <td>R$ {{ \Modules\AkauntingReports\Support\Money::format($member['devolvido_cents']) }}</td>
                            <td>R$ {{ \Modules\AkauntingReports\Support\Money::format($member['saldo_cents']) }}</td>
                            <td>{{ $member['pending'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhuma movimentação para os filtros selecionados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <h2>Extrato de movimentações</h2>
                <table class="w-full" aria-label="Extrato do capital social">
                    <thead><tr><th>Data</th><th>Cooperado</th><th>Movimentação</th><th>Valor</th><th>Situação</th><th>Documento</th><th>Observação</th></tr></thead>
                    <tbody>
                    @foreach ($class->movements as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['kind'] }}</td>
                            <td>R$ {{ \Modules\AkauntingReports\Support\Money::format($row['amount_cents']) }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['source'] }} #{{ $row['id'] }}</td>
                            <td>{{ $row['reason'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p>
                    <a href="{{ url($class->getUrl('export')) }}">Exportar CSV</a>
                    <a href="{{ url($class->getUrl('pdf')) }}">PDF</a>
                    <a href="{{ url($class->getUrl('print')) }}" target="_blank" rel="noopener">Imprimir</a>
                </p>
            @endif
        </div>
    </x-slot>
</x-layouts.admin>
