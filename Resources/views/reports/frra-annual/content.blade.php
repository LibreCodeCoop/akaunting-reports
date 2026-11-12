{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<div class="space-y-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div>
            <h3 class="text-xl font-semibold text-black">FRRA · {{ $class->selectedYear }}</h3>
            <p class="mt-1 text-sm text-gray-500">
                Retenções mensais por cooperado e por competência, inclusive documentos em rascunho.
                Valor registrado não significa pagamento realizado.
            </p>
        </div>
        <span class="text-xs text-gray-500">{{ $class->summary['members_count'] }} cooperados</span>
    </div>

    @if ($class->configurationError !== null)
        <p class="rounded-lg border border-yellow-200 p-4 text-yellow-700">{{ $class->configurationError }}</p>
    @else
        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2 border-b border-gray-200 pb-4">
            <div>
                <div class="text-xs text-gray-500">FRRA registrado no ano</div>
                <div class="text-xl font-semibold text-black tabular-nums">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['total_cents']) }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Retenções na produção</div>
                <div class="text-base font-semibold text-black tabular-nums">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['production_cents']) }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Complemento da fatura anual</div>
                <div class="text-base font-semibold text-black tabular-nums">
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['annual_cents']) }}
                </div>
            </div>
        </div>

        @if (($class->issues['unallocated_by_year'][$class->selectedYear]['items'] ?? 0) > 0)
            <p class="rounded-lg border border-yellow-200 p-3 text-xs text-yellow-800">
                Histórico do ano incompleto:
                {{ $class->issues['unallocated_by_year'][$class->selectedYear]['items'] }} itens descrevem
                competências múltiplas ou ambíguas, totalizando
                R$ {{ \Modules\AkauntingReports\Support\Money::format(
                    $class->issues['unallocated_by_year'][$class->selectedYear]['cents']) }}.
                Estes valores não foram distribuídos entre os meses nem incluídos no total,
                para evitar uma repartição sem evidências.
            </p>
        @endif

        @if ($class->summary['annual_only_months'] > 0 || $class->summary['draft_items'] > 0)
            <p class="text-xs text-gray-600">
                {{ $class->summary['annual_only_months'] }} competências complementadas exclusivamente por fatura anual;
                {{ $class->summary['draft_items'] }} itens provenientes de documentos em rascunho.
                O complemento não é somado novamente quando a competência consta na produção.
                <strong>†</strong> indica valor obtido somente da fatura anual.
            </p>
        @endif
        @if ($class->summary['annual_overlap_months'] > 0
            || $class->summary['duplicate_annual_months'] > 0
            || $class->issues['non_brl_count'] > 0
            || $class->issues['invalid_amount_count'] > 0
            || $class->issues['excluded_status_count'] > 0)
            <p class="rounded-lg border border-yellow-200 p-3 text-xs text-yellow-800">
                Atenção à conciliação:
                {{ $class->summary['annual_overlap_months'] }} competências presentes nas duas origens (priorizada a produção);
                {{ $class->summary['duplicate_annual_months'] }} competências com múltiplos itens anuais;
                {{ $class->issues['non_brl_count'] }} itens em outra moeda;
                {{ $class->issues['excluded_status_count'] }} itens cancelados ou com situação incompatível;
                {{ $class->issues['invalid_amount_count'] }} itens com valores não aceitos.
                @if ($class->summary['overlap_difference_cents'] !== 0)
                    Diferença acumulada entre origens coincidentes:
                    R$ {{ \Modules\AkauntingReports\Support\Money::format($class->summary['overlap_difference_cents']) }}.
                @endif
            </p>
        @endif

        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs" aria-label="Ordenação do FRRA">
            <span class="text-gray-500">Ordenar:</span>
            <x-sortablelink column="name" title="Cooperado" />
            <x-sortablelink column="total_cents" title="Total anual" />
            <x-sortablelink column="production_cents" title="Retido na produção" />
            <x-sortablelink column="annual_cents" title="Complemento anual" />
        </div>
        <div class="rounded-lg border border-gray-200 bg-white">
            <x-table aria-label="Retenções anuais de FRRA por cooperado" style="width: 100%;">
                <x-table.thead class="hidden lg:block">
                    <x-table.tr>
                        <x-table.th override="class" class="lg:w-3/12 min-w-0 px-2 py-3 text-xs text-left font-semibold text-black">Cooperado</x-table.th>
                        @foreach (['Jan – Mar', 'Abr – Jun', 'Jul – Set', 'Out – Dez'] as $quarterTitle)
                            <x-table.th override="class" class="lg:w-2/12 min-w-0 px-2 py-3 text-xs text-left font-semibold text-black">{{ $quarterTitle }}</x-table.th>
                        @endforeach
                        <x-table.th override="class" class="lg:w-1/12 min-w-0 px-2 py-3 text-xs text-left font-semibold text-black">Total</x-table.th>
                    </x-table.tr>
                </x-table.thead>
                <x-table.tbody>
                    @forelse ($class->summary['members'] as $member)
                        <x-table.tr class="flex flex-wrap lg:flex-nowrap lg:items-start border-b px-1 hover:bg-gray-50">
                            <x-table.td override="class" class="w-full lg:w-3/12 min-w-0 px-2 py-3 text-sm text-black">
                                <div class="font-medium whitespace-normal break-words">{{ $member['name'] }}</div>
                                @if ($member['annual_cents'] > 0)
                                    <div class="mt-1 text-xs text-gray-500">
                                        Complemento anual:
                                        {{ \Modules\AkauntingReports\Support\Money::format($member['annual_cents']) }}
                                    </div>
                                @endif
                            </x-table.td>
                            @foreach (range(0, 3) as $quarter)
                                <x-table.td override="class" class="w-6/12 lg:w-2/12 min-w-0 px-2 py-3 text-sm text-black">
                                    <div class="mb-1 text-xs font-semibold text-gray-500 lg:hidden">
                                        {{ ['Jan – Mar', 'Abr – Jun', 'Jul – Set', 'Out – Dez'][$quarter] }}
                                    </div>
                                    @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                        <div class="flex justify-between gap-2 text-xs leading-6 tabular-nums">
                                            <span class="text-gray-500">{{ $class->monthNames[$month] }}</span>
                                            <span class="whitespace-nowrap">
                                                @if ($member['counts'][$month] === 0)
                                                    <span class="text-gray-400">—</span>
                                                @else
                                                    {{ \Modules\AkauntingReports\Support\Money::format($member['months'][$month]) }}
                                                    @if ($member['sources'][$month] === 'annual')
                                                        <strong class="text-yellow-700" title="Competência proveniente apenas da fatura anual">†</strong>
                                                    @endif
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </x-table.td>
                            @endforeach
                            <x-table.td override="class" class="w-full lg:w-1/12 min-w-0 px-2 py-3 text-sm text-black">
                                <span class="text-xs font-semibold text-gray-500 lg:hidden">Total anual: </span>
                                <span class="font-semibold tabular-nums">{{ \Modules\AkauntingReports\Support\Money::format($member['total_cents']) }}</span>
                            </x-table.td>
                        </x-table.tr>
                    @empty
                        <x-table.tr><x-table.td class="w-full">Nenhum lançamento de FRRA para a seleção atual.</x-table.td></x-table.tr>
                    @endforelse
                    <x-table.tr class="flex flex-wrap lg:flex-nowrap lg:items-start bg-gray-50 border-t border-gray-300">
                        <x-table.td override="class" class="w-full lg:w-3/12 min-w-0 px-2 py-3 font-semibold text-black">TOTAL {{ $class->selectedYear }}</x-table.td>
                        @foreach (range(0, 3) as $quarter)
                            <x-table.td override="class" class="w-6/12 lg:w-2/12 min-w-0 px-2 py-3 text-sm text-black">
                                <div class="mb-1 text-xs font-semibold text-gray-500 lg:hidden">
                                    {{ ['Jan – Mar', 'Abr – Jun', 'Jul – Set', 'Out – Dez'][$quarter] }}
                                </div>
                                @foreach (range($quarter * 3 + 1, $quarter * 3 + 3) as $month)
                                    <div class="flex justify-between gap-2 text-xs leading-6 tabular-nums font-semibold">
                                        <span class="text-gray-500">{{ $class->monthNames[$month] }}</span>
                                        <span class="whitespace-nowrap">{{ \Modules\AkauntingReports\Support\Money::format($class->summary['months'][$month]) }}</span>
                                    </div>
                                @endforeach
                            </x-table.td>
                        @endforeach
                        <x-table.td override="class" class="w-full lg:w-1/12 min-w-0 px-2 py-3 font-bold tabular-nums text-black">
                            <span class="text-xs lg:hidden">Total anual: </span>
                            {{ \Modules\AkauntingReports\Support\Money::format($class->summary['total_cents']) }}
                        </x-table.td>
                    </x-table.tr>
                </x-table.tbody>
            </x-table>
        </div>
        <p class="text-xs text-gray-500">
            @if ($class->issues['undated_count'] > 0)
                Há {{ $class->issues['undated_count'] }} itens em todo o histórico com
                competência indefinida; não pertencem necessariamente ao ano exibido.
            @endif
            Competência informada no item, não data de emissão/pagamento. Documentos cancelados, duplicações de outras origens
            e itens sem competência verificável não integram os valores.
        </p>
    @endif
</div>
