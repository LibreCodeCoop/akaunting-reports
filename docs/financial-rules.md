<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Financial rules

This module adds native read-only Akaunting reports. All records are scoped to the selected company, and amounts are grouped by the financial contact or member identity configured in that company.

## Member capital

The balance includes confirmed contributions and production withholdings, less documented refunds. Unpaid or partially paid items, ambiguous classifications and unsupported currencies do **not** become confirmed capital. Confirmed movements with documentary reservations remain part of the confirmed balance but retain a separate warning. Reports display source-document links for reconciliation.

A member capital balance is not a register of subscribed or unpaid cooperative shares.

## Annual FRRA

Each month is determined by the explicit accounting competence on an item, not by issuance, due or settlement date. Production documents are the primary evidence. Annual reserve bills only supplement months absent from a member's production documents. Source conflicts are reported without double-counting.

Items with ambiguous monthly competence are shown separately when their year is identifiable; no arbitrary allocation is made. Draft documents are **registered**, not necessarily paid. The report does not infer funds available for settlement.

## Currency and numerical integrity

The active Akaunting company's default currency determines which records are eligible and how values are formatted. Numeric computations use integer hundredths. Currency formats with a different number of fractional digits require a dedicated financial-rule review before use; they must not be silently interpreted as two-decimal currencies.

Exported CSV values use machine-readable decimal values and include the configured currency code in their column headings.

## Release verification

Tests use synthetic records and isolated tools. A release also requires a read-only reconciliation at movement and member/month level against the authoritative source, a permission check and visual validation of filters and exports. Never commit private accounting exports, client identities, credentials or infrastructure details.
