<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Financial inconsistency audit — native report and dashboard

The read-only report is registered as a standard Akaunting report and widget.
From an authorized, isolated Akaunting environment, validate the company ID
without writes:

\`\`\`sh
php artisan akaunting-reports:install-audit --company=COMPANY_ID --dry-run
\`\`\`

After verifying tenant permissions and read-only reconciliation, run the same
command without \`--dry-run\` to register the report and grant its report/widget
permissions to admin roles. No payment, accounting or tax document is created,
corrected or cancelled. Additional viewers require an explicit native ACL grant.
The dashboard widget also checks the **report's read permission**.

Filters: assessment, severity, source, rule and reference year. Page size is
50 findings, sorted deterministically by severity, descending reference month
and stable occurrence ID; navigation never relies on offset-based SQL scans.
CSV and PDF export use all filtered findings, not just the visible page. Export: CSV using machine-readable
identifiers, integer-cent evidence and deterministic occurrence IDs.
The native print and PDF paths use a separate printable view. The dashboard
preview is limited to 500 documents; the full report is limited to 10,000.
If a scan is truncated, the warning must remain visible and the totals must
be interpreted as partial.

A status mismatch can reflect legitimate credits, adjustments, migration history
or reversals. Payment and fiscal invoices are distinct concepts. A fiscal gross
difference can also reflect discounts or the scope of an issued receipt.
The rules deliberately classify these cases as **suspected**, never confirmed
financial misconduct. Missing, invalid or inaccessible data creates an
**insufficient evidence** notice. Only genuine, demonstrable evidence could
justify adding a confirmed classification in a future version.

The native report and widget must undergo integration/authorization/regression
testing on disposable synthetic multi-company data before deployment, including:
report-only user, widget-only user, unauthorized user, cross-company row,
CSV/print/PDF, zero records, partial payments, cancellations, currency, and
optional NFS-e absent/present. Reconcile balances before/after execution and
cross-check occurrence amounts against source records in **read-only** mode.
Screenshots must be captured in homologation, never from private production data.
