<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Financial audit contract (catalog v1)

The first delivery of issue #7 is a pure evaluation core, not an installed report.

An **authorized, read-only reader** creates a Snapshot for one company and one
currency. Every source row must contain the same integer company_id as the
snapshot. Readers must additionally scope SQL queries and enforce native
authorization: validation of snapshot rows does not replace access control.

Each source explicitly identifies whether it is available. An available but
empty source yields no occurrences. An unavailable source causes dependent
rules to be skipped and one **insufficient/info** finding to be emitted. This
does not mean there was a financial discrepancy. The engine never writes.

A Catalog registers unique rule IDs, positive rule versions, and required
sources. Increment the rule version when the meaning of a rule changes.
Occurrence identity is the SHA-256 of company, rule ID/version, source, opaque
source ID and case key. Supporting evidence is deliberately excluded from the
identity. Findings are sorted by ID; repeated evaluation with identical
snapshots and rule versions produces identical findings.

Classifications are **confirmed**, **suspected**, and **insufficient**.
Severities are **critical**, **warning**, and **info**. Severity alone is not
proof of a financial error. A rule that is not applicable returns no finding.
Explanation and guidance use translation keys; raw private source text, names,
tax identifiers and credentials must never be placed in evidence.

The reader, document/payment rules, optional fiscal integrations, native
report, filters, export and widget are later deliverables. Source navigation
requires authorization at the target document route. No NFS-e issuance
implies payment and a transaction without a document is not automatically
irregular. Currency conversion requires verified source data.

Before release, perform integration and permission checks against a
disposable Akaunting with synthetic multi-company records, plus **read-only**
reconciliation of values and movement associations against authoritative
sources. CI alone cannot validate financial semantics. Do not publish real
company data or private screenshots.
