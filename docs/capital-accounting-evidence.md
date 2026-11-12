<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Capital social: posição contábil e evidência assemblear

O capital social não é saldo bancário. Capital subscrito, capital a integralizar, capital realizado e capital confirmado no extrato financeiro representam aspectos distintos e não podem ser inferidos uns dos outros.

CapitalAccountingPosition recebe um StatementSnapshot previamente revisado e um mapeamento explícito de contas analíticas externas para os papéis subscribed e unpaid. Com saldos a crédito negativos e contra-capital a débito positivo, calcula capital realizado = subscrito - a integralizar. O resultado traz em separado o capital confirmado pelo relatório financeiro e a diferença ainda não explicada. Não infere liquidez, caixa disponível ou entrada bancária. Mapas incompletos, contas duplicadas ou sinais incompatíveis bloqueiam a comparação.

CapitalAssemblyEvidence avalia **candidatos** a capitalização por deliberação assemblear e respectivas reversões. Exige identidade de empresa, beneficiário, deliberação, alocação, revisão, SHA-256 da fonte e um estado de revisão. Apenas uma aprovação proveniente de fonte de confiança, acompanhada de referência a lançamento contábil, pode tornar um evento elegível para reconhecimento. Aprovação textual em um payload não comprova autenticação do contador: a camada de ingestão deve validar as permissões, as referências e os originais.

A chave de alocação não depende da revisão do documento, evitando reconhecer novamente a mesma destinação após reapresentação. A revisão e o hash permanecem na evidência original. Reversões exigem referência a evento reconhecido previamente. **Elegível não significa lançado**: esta classe não produz movimentos compatíveis com o relatório e não altera totais. A integração fica bloqueada até validação da escrituração e individualização pela contabilidade.

## Verificação e homologação

Testes sintéticos cobrem capital bruto, a integralizar e realizado; divergências; mapeamentos sobrepostos; aprovações pendentes; ausência de lançamento; duplicações entre revisões; reversões; isolamento por empresa. Dados individuais reais e documentos das assembleias não pertencem ao código público.

Para uma integração posterior, a camada autorizada deverá fornecer movimentos patrimoniais individualizados e comprovados com anti-duplicação contra o extrato financeiro já reconhecido. O relatório somente leitura poderá então separar o novo tipo de movimento das integralizações diretas e retenções, sem criar faturas artificiais ou movimentação bancária.
