<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Document settlement checks (rule v1)

The `document_settlement` rule inspects a bounded, company-scoped batch of
invoices and bills, with their attached, non-deleted transactions. An authorized
caller must enforce native report permission and active company before invoking
the `DocumentSettlementReader`. The reader never makes writes and uses no
personal identifiers or external data sources.

The rule reports **suspicions requiring source review**, not accounting verdicts:
a paid document with no/insufficient linked payment, a draft or cancelled
document with linked payment, or linked payments greater than the document total.
A legitimate partial payment is not an inconsistency. Unlinked transactions
are not, by themselves, irregular. Foreign currency, unsupported payment
direction, ambiguous reversals or non-cent amounts create an
**insufficient-evidence** notice, not a proven mismatch.

The source ledger is filtered by company and soft-deletion; the reader fetches
at most 500 documents per batch, then only the linked transactions for those
document IDs. Traverse by monotonically increasing document ID, not by offset.
The report layer is responsible for presenting all batches in consistent order,
applying requested filters, and performing a second read-only reconciliation
against the original ledger before release. It must not describe a snapshot as
transactionally consistent without a verified database isolation strategy.

A document's issue month is currently an *approximate display period*, not an
accounting competence proven by journal or fiscal records. No NFS-e issuance
is interpreted as payment. No value is corrected, paid or cancelled.
