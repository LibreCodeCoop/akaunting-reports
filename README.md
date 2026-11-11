<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Akaunting Reports

Independent, reusable [Akaunting](https://akaunting.com/) Laravel module for additional native financial reports. It does not modify the Akaunting core or make payment, tax, or invoice changes.

## Capital social / Member capital

The first report presents a **read-only, confirmed balance** by member, a movement-level audit trail, and entries that require human review. It uses Akaunting's native report page, toolbar and filter bar. The default **position by member**
shows direct integralizations, production withholdings, refunds and confirmed stock balances.
Selecting a member opens their individual ledger with a chronological running balance.
The **movement ledger** is a separate paginated view (25 rows per page), with native controls
for view, date, member, movement type and review status. CSV, print and PDF follow the current
view while exports include all matching records, not only the visible page.
The tables use Akaunting's built-in `x-table` and `x-sortablelink` components,
with whitelisted, numeric-aware sorting applied to the complete dataset
**before** pagination. The member view defaults to the **highest count of
unconfirmed movements first**, with compact numeric badges for pending items and
separate warning markers. Other sortable columns can override that default. The report bar (`report-filter`) is the native Akaunting
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
