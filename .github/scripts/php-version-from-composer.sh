#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

manifest="${1:-composer.json}"
constraint=$(jq -er '.require.php | select(type == "string")' "$manifest")

# CI tests the lowest supported PHP minor version. Fail closed when the
# Composer constraint changes shape instead of guessing a runtime.
if [[ "$constraint" =~ ^\^([0-9]+)\.([0-9]+)(\.[0-9]+)?$ ]]; then
    printf '%s.%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
else
    printf 'Unsupported PHP constraint in %s: %s\n' "$manifest" "$constraint" >&2
    exit 1
fi
