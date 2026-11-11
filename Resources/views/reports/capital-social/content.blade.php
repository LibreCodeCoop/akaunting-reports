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
                    <strong>{{ $class->reviewCounts['awaiting_payment'] }}</strong> aguardando pagamento ·
                    <strong>{{ $class->reviewCounts['requires_review'] }}</strong> exigindo conferência.
                    @if ($class->capitalSummary['warnings_count'] > 0)
                        <strong>{{ $class->capitalSummary['warnings_count'] }}</strong> ressalva(s) em saldos confirmados.
                    @endif
                    <span class="text-gray-500">Valores não confirmados não são somados ao capital. A situação é recalculada na atualização.</span>
                </p>
            </div>
            @if ($class->capitalSummary['pending_count'] > 0)
                <a href="{{ $class->reportUrl('ledger', $class->selectedMember, 'revisar') }}" class="text-sm font-medium text-blue-600 hover:underline">
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
                    <p class="mt-1 text-sm text-gray-500">Clique em um cooperado para abrir seu extrato. Ordene por qualquer coluna ou use a barra nativa de filtros acima.</p>
                </div>
                <span class="text-sm text-gray-500">{{ count($class->sortedMembers) }} cooperados encontrados</span>
            </div>

            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <x-table aria-label="Posição do capital social por cooperado" style="min-width: 1140px;">
                    <x-table.thead>
                        <x-table.tr>
                            <x-table.th class="w-3/12"><x-sortablelink column="name" title="Cooperado" /></x-table.th>
                            <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="direct_cents" title="Integralização direta" /></x-table.th>
                            <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="retained_cents" title="Retenção na produção" /></x-table.th>
                            <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="devolvido_cents" title="Devoluções" /></x-table.th>
                            <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="saldo_cents" title="Saldo" /></x-table.th>
                            <x-table.th class="w-1/12" title="Pendências não confirmadas"><x-sortablelink column="pending" title="Pend." :arguments="['title' => 'Ordenar por pendências']" /></x-table.th>
                        </x-table.tr>
                    </x-table.thead>
                    <x-table.tbody>
                        @forelse ($class->memberPages as $member)
                            <x-table.tr>
                                <x-table.td class="w-3/12">
                                    <a href="{{ $class->reportUrl('ledger', $member['contact_id']) }}"
                                       class="font-medium text-blue-600 hover:underline">{{ $member['name'] }}</a>
                                </x-table.td>
                                <x-table.td kind="amount" class="w-2/12">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['direct_cents']) }}</x-table.td>
                                <x-table.td kind="amount" class="w-2/12">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['retained_cents']) }}</x-table.td>
                                <x-table.td kind="amount" class="w-2/12">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['devolvido_cents']) }}</x-table.td>
                                <x-table.td kind="amount" class="w-2/12 font-semibold">R$ {{ \Modules\AkauntingReports\Support\Money::format($member['saldo_cents']) }}</x-table.td>
                                <x-table.td class="w-1/12">
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap">
                                        @if ($member['pending'] > 0)
                                            <span class="inline-flex items-center justify-center rounded-full bg-yellow-50 px-2 py-1 text-xs font-semibold text-yellow-700"
                                                  title="{{ $member['pending'] }} {{ $member['pending'] === 1 ? 'movimentação não confirmada' : 'movimentações não confirmadas' }}"
                                                  aria-label="{{ $member['pending'] }} {{ $member['pending'] === 1 ? 'pendência' : 'pendências' }}">{{ $member['pending'] }}</span>
                                        @else
                                            <span class="text-gray-400" title="Sem pendências" aria-label="Sem pendências">—</span>
                                        @endif
                                        @if ($member['warnings'] > 0)
                                            <span class="inline-flex items-center gap-1 text-xs text-yellow-700"
                                                  title="{{ $member['warnings'] }} {{ $member['warnings'] === 1 ? 'ressalva' : 'ressalvas' }} em valores já confirmados"
                                                  aria-label="{{ $member['warnings'] }} {{ $member['warnings'] === 1 ? 'ressalva' : 'ressalvas' }}">
                                                <span class="material-icons text-sm" aria-hidden="true">report_problem</span>{{ $member['warnings'] }}
                                            </span>
                                        @endif
                                    </span>
                                </x-table.td>
                            </x-table.tr>
                        @empty
                            <x-table.tr><x-table.td class="w-full">Nenhum cooperado encontrado para os filtros aplicados.</x-table.td></x-table.tr>
                        @endforelse
                    </x-table.tbody>
                </x-table>
            </div>
            @if ($class->memberPages->hasPages())
                <div class="mt-4">{{ $class->memberPages->links() }}</div>
            @endif
        @else
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold text-black-400">Extrato de movimentações</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ count($class->movements) }} registros encontrados. Clique nos títulos para ordenar.
                        @if ($class->selectedMember !== null)
                            O saldo individual reflete toda a sequência cronológica, mesmo quando o filtro oculta lançamentos.
                        @else
                            Selecione um cooperado na barra de filtros para visualizar o saldo individual acumulado.
                        @endif
                    </p>
                </div>
                <span class="text-sm text-gray-500">25 lançamentos por página</span>
            </div>

            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <x-table aria-label="Extrato do capital social">
                    <x-table.thead>
                        <x-table.tr>
                            <x-table.th class="w-2/12"><x-sortablelink column="date" title="Data" /></x-table.th>
                            @if ($class->selectedMember === null)
                                <x-table.th class="w-2/12"><x-sortablelink column="name" title="Cooperado" /></x-table.th>
                            @endif
                            <x-table.th class="w-2/12"><x-sortablelink column="kind" title="Natureza" /></x-table.th>
                            <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="amount_cents" title="Valor" /></x-table.th>
                            @if ($class->selectedMember !== null)
                                <x-table.th kind="amount" class="w-2/12"><x-sortablelink column="running_balance_cents" title="Saldo individual" /></x-table.th>
                            @endif
                            <x-table.th class="w-2/12"><x-sortablelink column="status" title="Situação" /></x-table.th>
                            <x-table.th class="w-2/12"><x-sortablelink column="source" title="Origem" /></x-table.th>
                        </x-table.tr>
                    </x-table.thead>
                    <x-table.tbody>
                        @forelse ($class->movementPages as $row)
                            <x-table.tr>
                                <x-table.td class="w-2/12">{{ company_date($row['date']) }}</x-table.td>
                                @if ($class->selectedMember === null)
                                    <x-table.td class="w-2/12">{{ $row['name'] }}</x-table.td>
                                @endif
                                <x-table.td class="w-2/12">
                                    @switch($row['kind'])
                                        @case('integralizacao') Integralização direta @break
                                        @case('retencao') Retenção na produção @break
                                        @case('devolucao') Devolução @break
                                        @default Análise necessária
                                    @endswitch
                                </x-table.td>
                                <x-table.td kind="amount" class="w-2/12">
                                    @if ($row['status'] === 'confirmado')
                                        R$ {{ \Modules\AkauntingReports\Support\Money::format($row['amount_cents']) }}
                                    @else
                                        <span class="text-gray-500">Não confirmado</span>
                                        @if ($row['candidate_cents'] > 0)
                                            <div class="mt-1 text-xs text-yellow-700">R$ {{ \Modules\AkauntingReports\Support\Money::format($row['candidate_cents']) }} em análise</div>
                                        @endif
                                    @endif
                                </x-table.td>
                                @if ($class->selectedMember !== null)
                                    <x-table.td kind="amount" class="w-2/12">R$ {{ \Modules\AkauntingReports\Support\Money::format($row['running_balance_cents']) }}</x-table.td>
                                @endif
                                <x-table.td class="w-2/12">
                                    @php $reviewType = \Modules\AkauntingReports\Support\CapitalSocialReview::category($row); @endphp
                                    <span class="{{ $reviewType === 'confirmed' ? 'text-green-700' : 'text-yellow-700' }} font-medium">
                                        {{ \Modules\AkauntingReports\Support\CapitalSocialReview::label($row) }}
                                    </span>
                                </x-table.td>
                                <x-table.td class="w-2/12">
                                    @php
                                        $sourceRoute = $row['source'] === 'transaction' ? 'transactions.show'
                                            : ($row['document_type'] === 'bill' ? 'bills.show' : 'invoices.show');
                                    @endphp
                                    <a href="{{ route($sourceRoute, $row['id']) }}" class="text-blue-600 hover:underline"
                                       title="Abrir o lançamento de origem">
                                        {{ $row['source'] === 'document' ? 'Documento' : 'Transação' }} #{{ $row['id'] }}
                                    </a>
                                    @if ($row['status'] !== 'confirmado' || $row['reason'] !== '')
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ \Modules\AkauntingReports\Support\CapitalSocialReview::guidance($row) }}
                                        </div>
                                    @endif
                                </x-table.td>
                            </x-table.tr>
                        @empty
                            <x-table.tr><x-table.td class="w-full">Nenhuma movimentação encontrada para os filtros aplicados.</x-table.td></x-table.tr>
                        @endforelse
                    </x-table.tbody>
                </x-table>
            </div>
            @if ($class->movementPages->hasPages())
                <div class="mt-4">{{ $class->movementPages->links() }}</div>
            @endif
        @endif
    @endif
</section>
