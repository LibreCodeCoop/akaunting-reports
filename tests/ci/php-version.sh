#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

reader="$(git rev-parse --show-toplevel)/.github/scripts/php-version-from-composer.sh"
tempdir="$(mktemp -d)"
trap 'rm -rf "$tempdir"' EXIT

for case in '^8.3:8.3' '^8.4.2:8.4' '^9.0:9.0'; do
    constraint="${case%%:*}"
    expected="${case#*:}"
    printf '{"require":{"php":"%s"}}\n' "$constraint" > "$tempdir/composer.json"
    actual="$(bash "$reader" "$tempdir/composer.json")"
    if [[ "$actual" != "$expected" ]]; then
        printf 'Expected %s, got %s\n' "$expected" "$actual" >&2
        exit 1
    fi
done

for constraint in '>=8.3 <9.0' '*' '8.3'; do
    printf '{"require":{"php":"%s"}}\n' "$constraint" > "$tempdir/composer.json"
    if bash "$reader" "$tempdir/composer.json" >/dev/null 2>&1; then
        printf 'Unsupported constraint unexpectedly accepted: %s\n' "$constraint" >&2
        exit 1
    fi
done

if bash "$reader" "$tempdir/missing.json" >/dev/null 2>&1; then
    echo 'Missing Composer manifest unexpectedly accepted' >&2
    exit 1
fi

test "$(bash "$reader")" = "$(bash "$reader" composer.json)"
echo 'Composer PHP version resolver checks passed'
