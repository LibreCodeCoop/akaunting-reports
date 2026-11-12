<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Akaunting Reports

Independent, reusable [Akaunting](https://akaunting.com/) Laravel module for additional native financial reports. It does not modify the Akaunting core or make payment, tax, or invoice changes.

## FRRA anual / Monthly reserve matrix

The **FRRA Anual** report adds an independent read-only annual matrix:
one row per cooperative member, twelve months grouped in four compact quarter
columns, an annual total for each member, and monthly/yearly totals in a final
row. The native filter switches years and optionally selects a member. Its
member options come from the same company-scoped, year-specific FRRA ledger
as the totals: unrelated suppliers and repeated vendor contact names are
never offered. A cooperative's historical FRRA is retained even if their
Akaunting login is disabled or their HR employee record is absent. Different
financial contact IDs sharing a name are explicitly disambiguated rather
than silently merged.
The responsive layout avoids hidden horizontal scrolling. Native print/PDF
and CSV contain the complete list, not a page. By default, the member list
is sorted by cooperative member name, ascending. The native report editor
can instead save a default sort by annual total, production withholding,
or annual-bill supplement, with ascending/descending direction. Native
sorting links let viewers override the saved preference without changing
the underlying FRRA ledger or export totals.

Month/year comes from the **explicit competence in each document item**,
not the invoice issue, due or payment date. Draft bills are included as
*registered*, not *paid*. Both sources are configured per company:

- Production items with the configured category, document prefix and item
  name are the primary records of retained FRRA.
- Annual FRRA bills with a distinct category/prefix **supplement only months
  absent in that member's production items**. Those cells are marked (†).
- If both sources contain a month, production wins. Overlaps and different
  values are surfaced for review, never silently counted twice.
- Several legitimate production bills in the same month can contribute
  distinct amounts. Unrelated numbering series (e.g., a copy) do not qualify.
- Historic descriptions that combine months or omit a precise competence
  are not guessed or divided. Identified historical unallocated sums are
  shown separately. Cancelled, deleted, currency-mismatched or invalid
  items are excluded.

### Install annual FRRA

Identify the two **expense categories and exact document prefixes** of the
target company. First run dry-run using company-specific IDs:

    php artisan akaunting-reports:install-frra       --company=COMPANY_ID       --production-category=PRODUCTION_CATEGORY_ID       --annual-category=ANNUAL_FRRA_CATEGORY_ID       --production-prefix=PRODUCTION_DOCUMENT_PREFIX       --annual-prefix=ANNUAL_DOCUMENT_PREFIX       --item-name=FRRA --dry-run

After independent financial reconciliation, repeat without the dry-run
option. Registration is idempotent. Only report settings, the report row
and its native administrative read permission are written, never accounting
transactions, payments, contacts or cooperative user accounts.

For evidence and release criteria, see docs/frra-reconciliation.md.

## Capital social / Member capital

The first report presents a **read-only, confirmed balance** by member, a movement-level audit trail, and entries that require human review. It uses Akaunting's native report page, toolbar and filter bar. The default **position by member**
shows direct integralizations, production withholdings, refunds and confirmed stock balances.
Selecting a member opens their individual ledger with a chronological running balance.
The **movement ledger** is a separate paginated view (25 rows per page), with native controls
for view, date, member, movement type and review status. CSV, print and PDF follow the current
view while exports include all matching records, not only the visible page.
The tables use Akaunting's built-in `x-table` and `x-sortablelink` components,
with whitelisted, numeric-aware sorting applied to the complete dataset
**before** pagination. The default is a three-stage, per-report order:
**pending movements descending, then confirmed audit reservations descending**
(within the same visible review-status criterion), **last confirmed contribution
descending**, then **member name ascending**. A reservation does not become an
unconfirmed financial movement; it simply precedes members with no issues.
Configure each column and direction under Edit report; clicking a native
sortable column changes only the first criterion,
retaining the configured second and third tie-breakers. Repeated columns are
skipped and the contact ID guarantees deterministic final ordering. Members
without confirmed contributions come last when sorting by activity date in
either direction. Preferences never alter the financial ledger.
The member view uses Akaunting's native two-level first/second field pattern,
consolidating eight
fields into four native table columns: member/recent contribution,
direct/retained capital, balance/refunds, and pending counts/reasons.
Pending and audit reservations are distinguished within the same cell.
On narrow screens the rows wrap into two compact pairs rather than requiring
a wide table and an invisible horizontal scrollbar. Full reasons remain
available from the source ledger and CSV, while shortened labels make the
overview readable. Column sorting remains available for the original
financial fields, pending counts, and contribution dates. The report bar (`report-filter`) is the native Akaunting
report filtering system; it differs from the transaction/item index search bar.
It offers company-specific member selection, as-of date, overview pending-members
filter, and ledger confirmation, nature and reason filters.

**Review is derived, not approved manually.** Unpaid documents are labelled
"Awaiting payment" (they become eligible after payment is reconciled);
ambiguous classifications require correcting the underlying document or transaction.
There is no "mark resolved" flag and no manual override of the accounting balance.
A **confirmed movement with an audit reservation** remains in the confirmed
capital balance; its warning is listed separately from the genuinely
unconfirmed movements. This prevents inflating the pending count.
The report retains Akaunting's existing reports authorization boundary. The report is **not** a complete corporate capital register and does not infer subscribed but unpaid capital.

The calculation uses a configurable capital category, capital document item, and dedicated refund category code. It detects bank deposits, paid capital invoices, production withholdings, and documented refunds. Unpaid, partially paid, mixed, foreign-currency or otherwise ambiguous records remain marked for review and do not silently increase balances.

### Requirements

- Akaunting 3.3-compatible Laravel module loader (verify against the target version before installation).
- PHP 8.3+ with the dependencies and extensions of the host Akaunting installation.
- MySQL/MariaDB as supported by the installation.
- Permission to manage modules and reports for installation; the report itself uses Akaunting's native per-report read permission.

### Install (isolated environment first)

1. Place the **contents of this repository** inside Akaunting's `modules/AkauntingReports` folder. Do not modify `app/`, `vendor/`, or the Akaunting core.
2. Follow the installation's normal module registration/enable workflow. The host's module installation creates the read permission for the report; it is **not** granted to every user by default.
3. Locate the **category and item IDs in the specific company**. These are not universal IDs and must never be copied from another company or installation.
4. Validate without writes:

   ```sh
   php artisan akaunting-reports:install-capital --company=COMPANY_ID --category=CATEGORY_ID --item=ITEM_ID --dry-run
   ```

5. **Only after reconciled approval**, run the same command without `--dry-run` to provision that company's settings and report row. Optionally supply `--refund-code=...`, `--advance-category=...`, `--advance-item=...`, `--legacy-refund-category=...`, `--late-deduction-item=...`, `--excluded-expense-category=...`, using values verified in the same company. Historical aliases are opt-in and must not be inferred from another installation. No financial documents or transactions are changed.
6. Grant the required *read-akaunting-reports-reports-capital-social* permission only to authorized report viewers. Locate the report in the native **Reports** section.

Never test with credentials, copied personal records or dumps in this repository. Read [Reconciliation and release gate](docs/reconciliation.md) before any deployment. A syntactically correct or green CI run is not a substitute for reconciliation of real business records.

### Developer checks

```sh
php tests/run.php
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n 1 php -l
```

These checks cover pure financial rules, the native view contract and PHP syntax. They **do not** prove full browser integration or accounting totals; use a disposable Akaunting installation with synthetic records for integration, ACL, tenant isolation and export tests.

### Design boundaries

- The financial source of truth is **the selected Akaunting company**, never an external cooperative-production database.
- Configuration is **per company**. The module contains no hardcoded account identifiers or hostnames.
- Reconciliation findings must be resolved in the authoritative source, not hidden or overwritten by the report.
- Default behavior is conservative: uncertain financial entries contribute zero to confirmed balance and are displayed as pending.
- The report is read-only. It does not calculate cooperative production, create accounting movements or manage subscribed capital.

### Development status

**Pre-release / pull request under review.** The module requires full integration and
visual/usability review in the target Akaunting version before merging. A previous
read-only reconciliation showed transaction-level financial parity with the source ledger,
but each change must preserve that parity. Never commit private reconciliation output,
customer data, or machine identifiers.

## License

AGPL-3.0-or-later. See `LICENSE` and `LICENSES/AGPL-3.0-or-later.txt`.

## Native report editing and financial activity

The native Akaunting report editor supports changing the report name, description,
default view and three levels of sorting (column and direction for each).
These six preferences use the existing per-report settings object; no new
schema, core customization or user-level override is introduced. By default,
the priorities are Pendências (decrescente), Última integralização
(decrescente), and Cooperado (crescente). A native column-header click
replaces only the primary sort for that view, preserving both saved tie-breakers.
If the same column is selected more than once, redundant comparisons are
ignored. The ledger retains its independent date sorting. Each field supplies
the standard attributes array expected by the Akaunting form. Changes to
report preferences never alter financial classifications or balances.

The member overview offers Any pending or audit reservation, plus the
individual categories, and shows a sortable latest confirmed contribution
date. Its activity filter covers the last 90, 180 or 365 days, or no
contribution in the last 365 days. These are precisely financial activity
signals, not proof that cooperative membership is legally active or inactive.

An independent Akaunting login filter distinguishes linked users enabled for
this company, disabled/unassigned users and contacts without a linked user.
The report does not provision, enable or disable accounts. The standard
Akaunting customer portal exposes customer sales invoices, not vendor bills
or cooperative production documents. Granting access to the latter requires
a separate own-record authorization design and negative cross-user tests;
do not grant a broad administration role as a workaround.
