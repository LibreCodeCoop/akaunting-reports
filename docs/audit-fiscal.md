<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Optional NFS-e reconciliation (rule v1)

The optional fiscal reader uses the verified `nfse_receipts` table contract from
`akaunting-nfse`, including its many-receipts-per-invoice migration and
authorized XML snapshot. Fiscal receipt ownership is established by an inner
join to an Akaunting invoice **inside the active company**. It does not
read raw XML, issue or cancel NFS-e, or retrieve taxpayer identities.

When the module/table is missing, its source is `available=false`: the
pure engine emits an **insufficient/informational** source notice. A missing
fiscal receipt for an invoice is not, by itself, a discrepancy. Cancelled or
substituted receipts are historical and excluded from the active comparison.
Issuance never proves payment.

An active receipt with a valid authorized-XML snapshot hash format can be
compared with an explicitly linked accounting invoice in the same company
and currency. When several active fiscal receipts refer to the same invoice, compare the
**sum** of authorized gross service values with the accounting invoice total;
do not compare each partial receipt individually. If any active receipt lacks
verifiable source evidence, the entire comparison is insufficient.
A difference between that authorized aggregate and the invoice
total is a **suspicion**, not a confirmed discrepancy: discounts, aggregation,
classification and other legitimate causes may explain it. A syntactically
valid snapshot hash does **not** prove the underlying XML was revalidated.
Unknown source, missing snapshot, invalid amounts and unsupported currency
cannot produce a confirmed financial finding.

The contract intentionally does not infer CSLL/ISS/PIS/COFINS incidence,
capital social or cooperative reserve classifications. Those require verified
configurations and accounting policy before new rules can be trusted.
