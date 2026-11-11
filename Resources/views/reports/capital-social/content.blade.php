{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}

{{-- Included INSIDE components.reports.show: Akaunting owns the toolbar, filters and page layout. --}}
<section class="my-6">
    @if ($class->configurationError)
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            {{ $class->configurationError }}
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-black-400">Posição do capital social</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Até {{ company_date($class->asOf) }} ·
                    {{ $class->capitalSummary['members_count'] }} cooperados ·
                    Valores confirmados, em reais.
                </p>
            </div>
            @if ($class->selectedMember !== null)
                <a href="{{ $class->reportUrl('overview') }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
                    <span class="material-icons text-base" aria-hidden="true">arrow_back</span>
                    Voltar à posição geral
                </a>
            @endif
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">account_balance_wallet</span>
                    Saldo confirmado
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['total_cents']) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">Integralizações + retenções − devoluções</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">add_circle_outline</span>
                    Integralização direta
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['direct_cents']) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">Contribuições recebidas diretamente</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">receipt_long</span>
                    Retido na produção
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['retained_cents']) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">Descontos de capital social confirmados</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="material-icons text-lg" aria-hidden="true">remove_circle_outline</span>
                    Capital devolvido
                </div>
                <div class="mt-3 text-2xl font-semibold text-black-400">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['devolvido_cents']) }}
                </div>
                <p class="mt-2 text-xs text-gray-500">Restituições já descontadas do saldo</p>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-center gap-3">
                @if ($class->capitalSummary['pending_count'] > 0)
                    <span class="material-icons text-yellow-600" aria-hidden="true">info_outline</span>
                @else
                    <span class="material-icons text-green-600" aria-hidden="true">check_circle_outline</span>
                @endif
                <p class="text-sm text-black-400">
                    <strong>{{ $class->capitalSummary['confirmed_count'] }}</strong> movimentações confirmadas ·
                    <strong>{{ $class->capitalSummary['pending_count'] }}</strong> aguardando conferência.
                    <span class="text-gray-500">Pendências não são somadas ao capital.</span>
                </p>
            </div>
            @if ($class->capitalSummary['pending_count'] > 0)
                <a href="{{ $class->reportUrl('ledger', $class->selectedMember) }}" class="text-sm font-medium text-blue-600 hover:underline">
                    Conferir extrato
                </a>
            @endif
        </div>

        <nav aria-label="Visões do relatório" class="mb-6 flex gap-6 border-b border-gray-200">
            <a href="{{ $class->reportUrl('overview') }}"
               class="{{ $class->tab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-black-400' }} inline-flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-medium"
               @if ($class->tab === 'overview') aria-current="page" @endif>
                <span class="material-icons text-lg" aria-hidden="true">people_outline</span>
                Por cooperado
            </a>
            <a href="{{ $class->reportUrl('ledger', $class->selectedMember) }}"
               class="{{ $class->tab === 'ledger' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-black-400' }} inline-flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-medium"
               @if ($class->tab === 'ledger') aria-current="page" @endif>
                <span class="material-icons text-lg" aria-hidden="true">list_alt</span>
                Extrato de movimentações
            </a>
        </nav>

        @if ($class->tab === 'overview')
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold text-black-400">Capital por cooperado</h3>
                    <p class="mt-1 text-sm text-gray-500">Selecione um cooperado para consultar seu extrato e a evolução do saldo.</p>
                </div>
                <span class="text-sm text-gray-500">{{ $class->capitalSummary['members_count'] }} registros</span>
            </div>

            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full rp-border-collapse text-sm" aria-label="Posição do capital social por cooperado">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">Cooperado</th>
                            <th scope="col" class="px-4 py-3 text-right">Integralização direta</th>
                            <th scope="col" class="px-4 py-3 text-right">Retenção na produção</th>
                            <th scope="col" class="px-4 py-3 text-right">Devoluções</th>
                            <th scope="col" class="px-4 py-3 text-right">Saldo</th>
                            <th scope="col" class="px-4 py-3 text-center">Conferência</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($class->memberPages as $member)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ $class->reportUrl('ledger', $member['contact_id']) }}"
                                       class="font-medium text-blue-600 hover:underline">{{ $member['name'] }}</a>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['direct_cents']) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['retained_cents']) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['devolvido_cents']) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-semibold">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['saldo_cents']) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($member['pending'] > 0)
                                        <span class="inline-flex rounded px-2 py-1 text-xs text-yellow-700 bg-yellow-50">{{ $member['pending'] }} a conferir</span>
                                    @else
                                        <span class="inline-flex rounded px-2 py-1 text-xs text-green-700 bg-green-50">Sem pendências</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhum cooperado encontrado para os filtros aplicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($class->memberPages->hasPages())
                <div class="mt-4">{{ $class->memberPages->links() }}</div>
            @endif
        @else
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold text-black-400">Extrato de movimentações</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ count($class->movements) }} registros encontrados.
                        @if ($class->selectedMember !== null)
                            O saldo individual mostra a evolução histórica, inclusive com filtros de situação.
                        @else
                            Use o filtro de cooperado para consultar um saldo individual acumulado.
                        @endif
                    </p>
                </div>
                <span class="text-sm text-gray-500">25 lançamentos por página</span>
            </div>

            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="w-full rp-border-collapse text-sm" aria-label="Extrato do capital social">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">Data</th>
                            <th scope="col" class="px-4 py-3 text-left">Cooperado</th>
                            <th scope="col" class="px-4 py-3 text-left">Natureza</th>
                            <th scope="col" class="px-4 py-3 text-right">Valor</th>
                            @if ($class->selectedMember !== null)
                                <th scope="col" class="px-4 py-3 text-right">Saldo individual</th>
                            @endif
                            <th scope="col" class="px-4 py-3 text-left">Situação</th>
                            <th scope="col" class="px-4 py-3 text-left">Origem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($class->movementPages as $row)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">{{ company_date($row['date']) }}</td>
                                <td class="px-4 py-3">{{ $row['name'] }}</td>
                                <td class="px-4 py-3">
                                    @switch($row['kind'])
                                        @case('integralizacao') Integralização direta @break
                                        @case('retencao') Retenção na produção @break
                                        @case('devolucao') Devolução @break
                                        @default Conferência necessária
                                    @endswitch
                                </td>
                                <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                    @if ($row['status'] === 'confirmado')
                                        R$ {{ \Modules\AkauntingReports\Support\Money::format($row['amount_cents']) }}
                                    @else
                                        <span class="text-gray-500">Não contabilizado</span>
                                        @if ($row['candidate_cents'] > 0)
                                            <div class="mt-1 text-xs text-yellow-700">
                                                R$ {{ \Modules\AkauntingReports\Support\Money::format($row['candidate_cents']) }} em análise
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                @if ($class->selectedMember !== null)
                                    <td class="px-4 py-3 text-right whitespace-nowrap">R$ {{ \Modules\AkauntingReports\Support\Money::format($row['running_balance_cents']) }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    @if ($row['status'] === 'confirmado')
                                        <span class="text-green-700">Confirmada</span>
                                    @else
                                        <span class="text-yellow-700 font-medium" title="{{ $row['reason'] }}">A conferir</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $sourceRoute = $row['source'] === 'transaction' ? 'transactions.show'
                                            : ($row['document_type'] === 'bill' ? 'bills.show' : 'invoices.show');
                                    @endphp
                                    <a href="{{ route($sourceRoute, $row['id']) }}" class="text-blue-600 hover:underline"
                                       title="Abrir o lançamento de origem">
                                        {{ $row['source'] === 'document' ? 'Documento' : 'Transação' }} #{{ $row['id'] }}
                                        <span class="material-icons align-middle text-sm" aria-hidden="true">open_in_new</span>
                                    </a>
                                    @if ($row['reason'] !== '')
                                        <div class="mt-1 text-xs text-gray-500">{{ $row['reason'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $class->selectedMember !== null ? 7 : 6 }}" class="px-4 py-8 text-center text-gray-500">
                                    Nenhuma movimentação encontrada. Ajuste os filtros na barra acima.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($class->movementPages->hasPages())
                <div class="mt-4">{{ $class->movementPages->links() }}</div>
            @endif
        @endif
    @endif
</section>
