<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Relatórios financeiros sob medida para o Akaunting

**Decisões melhores começam com dados financeiros claros.**

O [Akaunting](https://akaunting.com/) reúne informações financeiras da sua organização. A **[LibreCode](https://librecode.coop/)** desenvolve relatórios personalizados para transformar esses registros em indicadores, conciliações e informações que fazem sentido para cada operação, sem substituir o sistema de origem.

Este repositório reúne nosso módulo de relatórios nativos, reutilizável em instalações do Akaunting. Os exemplos atuais são:

- **Capital Social:** posição por cooperado, integralizações, retenções, devoluções e extrato auditável, com valores pendentes separados dos confirmados.
- **FRRA Anual:** retenções por cooperado e competência, visão mensal e anual, confronto entre documentos de produção e faturas anuais e indicação de divergências.

Ambos utilizam a interface de relatórios do Akaunting, com filtros e exportação. A apuração é somente leitura; não cria pagamentos nem altera saldos.

## Sua organização precisa de outro relatório?

Podemos desenvolver demonstrativos de receitas e despesas, relatórios gerenciais, conciliação, acompanhamento de contratos, indicadores por centro de custo e integrações com outros sistemas — respeitando as regras de negócio e as fontes de dados da sua organização.

**[Converse com a LibreCode](https://librecode.coop/)** sobre consultoria, desenvolvimento, implantação e suporte para soluções de gestão financeira em software livre.

## Para quem desenvolve

O módulo utiliza o sistema nativo de extensões e relatórios do Akaunting. As regras financeiras são separadas da leitura dos dados, permitindo testes sem credenciais ou bases de produção.

Consulte a [arquitetura e as regras financeiras](docs/financial-rules.md), as [instruções de desenvolvimento](docs/development.md) e o [guia de contribuição](CONTRIBUTING.md). Contribuições são bem-vindas por meio das issues e dos pull requests.
