<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Optional FRRA Annual classification checks

The reader uses only the five explicit company-specific mappings of the
existing FRRA Annual report. Missing configuration means insufficient
evidence. It reuses the reviewed source reader and strips personal names,
free text, tax identifiers and all other sensitive fields before evaluation.

The rule compares an accounting contact and the document's explicit monthly
competence, rather than payment dates. Production withholdings and annual
bills may represent the same reserve obligation, not separate bank deposits.
Where both exist, differing monthly totals become suspected cases only.
Multiple distinct annual documents without a production source are also
suspected cases, not proof of duplicate accounting or fraud.

No category IDs or fiscal incidence rules are hard-coded. Source rows with
missing competence, excluded status or invalid amounts are not force-mapped
to a period. Further manual source reconciliation is required to establish
their completeness. No invoice, transaction or payment is changed.
