{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.print>
    <x-slot name="title">{{ $class->model->name }} · {{ $class->selectedYear }}</x-slot>
    <x-slot name="content">
        <h2>{{ $class->model->name }} · {{ $class->selectedYear }}</h2>
        <p>FRRA por competência mensal e por cooperado; valores registrados não representam necessariamente pagamentos.</p>
        @if ($class->configurationError)
            <p>{{ $class->configurationError }}</p>
        @else
            <p>
                <strong>Total registrado:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['total_cents']) }} ·
                <strong>Retido na produção:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['production_cents']) }} ·
                <strong>Complemento anual:</strong>
                R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['annual_cents']) }}
            </p>
            @if (($class->issues['unallocated_by_year'][$class->selectedYear]['items'] ?? 0) > 0)
                <p><strong>Atenção: histórico incompleto.</strong>
                    {{ $class->issues['unallocated_by_year'][$class->selectedYear]['items'] }} itens
                    com competências ambíguas, no valor de
                    R$ {{ \Modules\AkauntingReports\Support\Money::format(
                        $class->issues['unallocated_by_year'][$class->selectedYear]['cents']) }},
                    não foram distribuídos nos meses nem adicionados aos totais.</p>
            @endif
            <table class="w-full">
                <thead>
                    <tr>
                        <th>Cooperado</th>
                        <th>Jan – Mar</th><th>Abr – Jun</th><th>Jul – Set</th><th>Out – Dez</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($class->summary['members'] as $member)
                        <tr>
                            <td>{{ $member['name'] }}</td>
                            @foreach (range(0, 3) as $quarter)
                                <td>
                                    @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                        <div>{{ $class->monthNames[$month] }}:
                                            @if ($member['counts'][$month] > 0)
                                                {{ \Modules\AkauntingReports\Support\Money::format($member['months'][$month]) }}
                                                @if ($member['sources'][$month] === 'annual') † @endif
                                            @else
                                                —
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                            <td>{{ \Modules\AkauntingReports\Support\Money::format($member['total_cents']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>TOTAL {{ $class->selectedYear }}</th>
                        @foreach (range(0, 3) as $quarter)
                            <th>
                                @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                    <div>{{ $class->monthNames[$month] }}:
                                        {{ \Modules\AkauntingReports\Support\Money::format($class->summary['months'][$month]) }}</div>
                                @endforeach
                            </th>
                        @endforeach
                        <th>{{ \Modules\AkauntingReports\Support\Money::format($class->summary['total_cents']) }}</th>
                    </tr>
                </tbody>
            </table>
            <p>† = competência complementada apenas pela fatura anual de FRRA. Documentos em rascunho estão incluídos;
                pagamentos realizados não são inferidos.</p>
        @endif
    </x-slot>
</x-layouts.print>
