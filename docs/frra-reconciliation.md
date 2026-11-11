<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Annual FRRA reconciliation and release gate

This is a read-only inventory of monthly FRRA amounts documented for each
production competence, not an inferred cash-basis or tax report. Draft bills
are not necessarily paid. FRRA is distinct from statutory cooperative funds.

## Data sourcing and double-counting controls

1. Configure company ID, two distinct expense categories and exact document
   prefixes; do not use universal IDs or hostnames in this module.
2. Select only document items with the exact configured FRRA name and a
   verified competence, described in the form
   Referente ao ano/mês: YYYY-MM. Issue/payment dates are not a substitute.
3. Production items have priority. Annual FRRA bills fill only member/month
   combinations absent from production, never the same retention twice.
   The member filter is derived from these verified sources for the selected
   year, never from the entire vendors list or from login/HR availability.
   Financial reporting is not a legal membership register.
4. Warn when sources overlap and their amounts disagree. Distinct production
   documents may represent legitimate multiple payments in one competence.
5. Multiple-competence historic items remain unallocated, not guessed or split
   equally; separately disclose their year and amount when identifiable.
6. Exclude cancelled/deleted documents, mismatched currencies and invalid
   monetary precision. All arithmetic uses integer cents.

## Release verification

- Synthetic tests for 12 months, year boundaries, cent precision,
  multiple production entries, annual supplement and conflicting sources.
- Compare actual member/month and monthly/year totals with an independent
  read-only accounting query, without storing names or IDs in any artifact.
- Render the native Akaunting report editor, year filter, printable version
  and CSV. Verify responsive quarter-group cells without horizontal scroll.
- Check admin-only read permission and unchanged Capital Social report.
- Deploy a signed version with a rollback artifact; verify live totals again.

Never commit real personal identifiers, contact/financial data, confidential
server names, secrets, internal URLs or accounting dumps.
