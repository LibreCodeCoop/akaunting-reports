<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Reviewed accounting snapshot contract (v1)

This is a **read-only foundational contract**, not an importer, accounting ledger, persistence layer or authorization boundary. Keep original documents and confidential account mappings outside the public repository.

`StatementSnapshot::normalize()` requires `schema_version: 1`, positive `company_id`, stable `statement_id`, explicit `revision`, inclusive ISO dates `period_start` and `period_end`, uppercase three-letter `currency`, lower-case SHA-256 `source_sha256` of the original source, and a nonempty `accounts` list. Each leaf account has `code`, `name` and integer `opening_cents`, `debit_cents`, `credit_cents`, `closing_cents`.

The canonical sign convention is debit-based: **closing = opening + debits - credits**. Credit balances are negative. Debits and credits are nonnegative. Account rows must be disjoint leaf accounts: do not include both a parent total and its children. An import adapter must reject duplicate subtotals, unknown decimal precision, ambiguous classifications and unverified PDF extraction. Source hash, statement identity and revision are separate; a newer version must never silently replace the earlier evidence.

`StatementReconciliation::compare($external, $internal, $mapping)` accepts two reviewed, same-company, same-period, same-currency snapshots and maps external account codes explicitly to internal codes. Multiple leaf accounts may map to one internal account. It returns `matched`, `divergent` or `incomplete` plus deltas and unmapped/invalid-target codes. Unmapped accounts never count as a match. No banking transaction is created. **Capital allocated from surpluses is not bank income.**

## Integration gate

Before live import, choose an authorized owner for persistence, version history, access control and retention (potentially a separate import module). Implement reviewed parsers, preserve source hashes, human acceptance and mapping revision, and agree on a correct accounting-basis internal comparison source. Akaunting's banking categories alone are not a double-entry general ledger. Do not compare cash-basis transactions mechanically with accrual statement balances. Integration and UI tests must use synthetic records for two different account plans.
