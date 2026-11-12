<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Optional member-capital review checks

Requires the already configured capital category and item of the active
company, plus native permission to read the Member Capital report. The reader
reuses the established CapitalSocialLedger and its stable review-code mapping.
It discards member names, contact and taxpayer identities, and free text before
building machine-only audit evidence.

Unpaid capital and partial payments are not financial errors by default.
Cancelled documents are also not automatically mismatches. Excess payments,
ambiguous capital lines and conflicting document links are review signals
classified as **suspected**, never confirmed financial errors. Unsupported
currencies and ambiguous classification are **insufficient evidence**.

Evidence cents are unconfirmed candidates, not balance adjustments. A
withholding in a cooperative production bill is not a separate bank receipt.
No invoices, accounting movements, reserves or payments are generated or
mutated by these checks. Missing mappings are explicitly informational.
