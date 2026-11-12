<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Contributing

Use a focused branch and pull request. Every commit needs a Conventional Commits subject (`feat:`, `fix:`, `test:`, etc.), a matching DCO `Signed-off-by:` trailer and a cryptographically verified signature. Do not use someone else's signing identity. An approved reviewer must verify CI, accounting semantics, authorization and cross-company isolation before merge.

Run `composer test:unit` and PHP syntax checks before submission. Add negative tests for new financial cases, keep fixtures synthetic, and update the reconciliation document when changing classification rules. Never commit production data, copied customer files, environment variables, private URLs, diagnostic dumps, secrets or infrastructure hostnames.
