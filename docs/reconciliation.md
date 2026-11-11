<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Capital-social reconciliation and production release gate

## Scope and data handling

Use only the authorized financial environment or an encrypted, time-limited isolated snapshot. **Do not commit real names, personal identifiers, URLs, bank details, documents, CSV exports, database exports, credentials, or hostnames.** Any evidence shared publicly must use synthetic fixtures. Production comparisons remain private and read-only.

The reference is the existing cooperative-production capital-social ledger at the same financial cutoff. The new native report must use the corresponding company's underlying Akaunting financial records. The reference's implementation must be pinned to a checked revision; do not compare one historical revision with another without recording it in the private audit log.

## Required comparisons

1. Confirm company scope and category/item mappings. Identify every supported capital transaction and document item in the selected company.
2. Produce a **read-only** reference snapshot and a native-report snapshot with the **same as-of cutoff** and same timezone/currency semantics.
3. Reconcile at movement granularity: canonical member identity; document/transaction source and ID; movement type; date; confirmed amount in integer cents; and review status/reason. Do not compare summary totals alone.
4. Reconcile per member: confirmed integralized capital; confirmed refunds; total confirmed balance; pending count; and negative balance alerts. Verify the grand total equals the sum of members, but equality of totals alone is insufficient.
5. Explicitly test unpaid and partial invoices, payments after cutoff, cancellations, deleted records, zero-net production bills, advance offsets, mixed item lines, refunds, currency mismatches, duplicate document-payment representation and historical post-payment deductions.
6. Verify the native permission prevents unauthorized show, CSV, PDF and print access. Verify changing company or filter cannot read another company's records. Verify malformed input is rejected before any database query.
7. Validate expected report registration, module enable/disable, translations, export CSV injection protections, pagination/performance on representative synthetic volumes, and no accounting writes.

## Blocking conditions

**Do not deploy** when any confirmed amount differs without a reviewed and documented explanation; member identity mapping is ambiguous; noncash capital is assumed integralized without evidence; payment or refund is double counted; or a negative/unsupported entry is silently accepted. Resolve the source discrepancy, write a regression test with synthetic values, then compare again. Do not modify the production accounting history to force agreement.

The inherited cooperative-production ledger has special logic for legacy advances, certain refund descriptions and post-payment document deductions. The current prototype is stricter in some of these cases; a smaller total can therefore signal an **unresolved implementation difference**, not an accounting correction. A reconciliation decision is required before rollout.

## Release decision

- **Not approved**: review and tests incomplete; no production changes.
- **Approved**: source revision and the release candidate SHA known, all finance differences reconciled, independent reviewer signs off, native ACL and multi-company regressions pass, GitHub CI passes on the published head.
- **Rollout**: install through the approved standard deployment procedure, register report for the intended company, check existing financial balance and unrelated modules, monitor read-only behavior.
- **Rollback**: disable the new module or remove the report registration **without modifying underlying financial records**. Preserve an audit log of the action in the private operations system; do not publish it.
