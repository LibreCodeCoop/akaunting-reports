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

## Historical financial classifications

Legacy refund category IDs and late-deduction item IDs are optional, company-specific configuration.
Only explicit refunds with a matching description and a configured historical category qualify.
Excess payments on production bills are accepted as confirmed retentions only when a non-capital
negative line was added after the last payment and its amount matches the discrepancy exactly.
Keep such cases as confirmed *with an audit warning*. Missing evidence remains pending; do not
silently count the amount. Validate these cases against the existing cooperative-production
ledger before enabling the module.

The external investment expense category is another optional company-specific ID.
Entries from investments **owned by** the company in other cooperatives are not
member shares **held in** the company and must not be mixed into this ledger.
When a transaction references an unrelated document, keep it as a pending
candidate; matching capital invoices must remain deduplicated.

## Review state and refresh

The dashboard reports confirmed capital separately from candidate movements. Pending
entries are not integralized, even if a candidate value is visible. The source ledger
is queried on each report refresh, and classification is re-evaluated; no review
status is stored by this module. An unpaid document is *awaiting payment*, not
necessarily an error; it becomes eligible when settlement records satisfy the
recognition rules. Incomplete or ambiguous records require correction and
reconciliation at the Akaunting source. The report never offers a manual approval
control capable of changing financial classification.

The native Akaunting report filter bar is used for as-of date, selected member,
confirmation state, movement type and review reason. Native sortable column links
preserve the search query. Sorting is allowlisted and applied to all matching rows
before pagination; exports likewise include the matching sorted dataset.

A confirmed movement can carry an audit warning without becoming pending.
The overview displays audit reservations separately so the number of
unconfirmed movements agrees with the detailed ledger.

The member overview defaults to **latest confirmed positive integralization
date descending**, placing members without confirmed integralizations last.
Native sortable links allow the user to select other columns, including pending
count. The compact member view uses four native two-line columns, like the native
invoice list: member/date, direct/retained contribution, balance/refunds,
and the combined pending/warning/reason cell. Long detailed reason strings are
available in tooltips and the individual ledger; the overview uses short labels.
The same underlying read-only data remains in CSV and printed views.
Mobile rows wrap into two pairs, avoiding a forced 1520-pixel table width.

## Historical activity, membership and account security

Activity based on the most recent confirmed positive capital movement
(including withholdings on settled production bills) is a financial proxy.
It must never be labelled official active/inactive membership. Confirmed
refunds, unpaid drafts and unconfirmed candidate movements do not advance the
last-contribution date.

Filters for enabled Akaunting users use only explicitly linked contact.user_id
records with user-company assignment. They do not prove cooperative membership.
Contacts with matching e-mail but no verified link must remain unlinked. Never
provision new accounts, re-enable existing accounts or assign financial
permissions based on presence in this report; a separate member-only portal
must enforce authenticated own-contact scoping.

The shipped Akaunting stylesheet compiles lg: responsive classes used by the
member table, but not the corresponding md: width classes. The compact
mobile sorting bar exposes recency, pending count and balance, with the other
native sort links available under a collapsible section.
