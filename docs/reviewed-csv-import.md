<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Importação revisada de dados contábeis

A classe ReviewedStatementCsv é um adaptador **somente leitura** de CSV classificado por um responsável humano. Recebe metadados da versão 1 de StatementSnapshot, inclusive company_id, período, revision e hash SHA-256 do documento contábil original. Um componente externo precisa conferir esse hash contra o original protegido; o CSV pode ser um derivado do XLSX ou do sistema do escritório. Não importar PDF por extração automática sem revisão.

O mapeamento entre colunas externas e campos canônicos é explícito, por escritório/organização. Cada linha precisa identificar row_type=leaf. Subtotais, pais, duplicações, linhas ambíguas, números arredondados, separadores de milhar e precisão além dos centavos são recusados. Suporta separadores de coluna vírgula, ponto e vírgula ou tabulação, e separador decimal configurado. Limites: 2 MiB e 10.000 linhas de contas. O texto bruto permanece fora do repositório.

StatementRevisionRegistry é uma função pura de preparação: a revisão exata repetida é idempotente; reutilizar a mesma chave de empresa + demonstrativo + revisão com conteúdo diferente falha; uma revisão nova fica pending_review e preserva o histórico. A classe NÃO concede aprovação e NÃO grava dados.

## Fronteira de integração

A persistência, autenticação, autorização por empresa, retenção, armazenamento imutável do documento e confirmação do revisor devem ficar em um componente de importação com permissões próprias. O módulo AkauntingReports permanece somente leitura e só poderá consumir dados com revisão comprovada. Manter também o hash do CSV derivado, a versão do mapeamento e a causa de qualquer divergência.

A conciliação real depende de fonte contábil compatível com o regime do demonstrativo. Categorias bancárias de caixa não equivalem a razão geral por partidas dobradas. Não criar ajustes, pagamentos, faturas ou lançamentos contábeis automáticos.

Os testes usam exclusivamente dois planos de contas sintéticos; interfaces de importação, armazenamento e ACL continuam sujeitos a integração separada.
