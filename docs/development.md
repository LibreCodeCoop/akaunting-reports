<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Development and verification

Requires PHP 8.3 or later, Composer 2 and a compatible Akaunting installation for integration testing.

Development tools are isolated under `vendor-bin`; they are not loaded by Akaunting in production.

```sh
composer install --no-plugins --no-scripts
composer --working-dir=vendor-bin/phpunit install
composer --working-dir=vendor-bin/psalm install
composer lint
composer test:unit
composer psalm
```

The CI runs separate PHP Syntax, PHPUnit, Psalm Static Analysis and REUSE Compliance workflows. PHP jobs use the lowest minor version allowed by the root `composer.json` PHP constraint, resolved by a shared action. The resolver rejects unsupported constraint syntax instead of silently choosing a different version. Dependency updates are handled through Dependabot for GitHub Actions, Composer and isolated tools.

The pure unit tests cover business invariants (allocation, conflicts, money precision and sorting), not a coverage percentage. Database access and HTTP permissions require integration tests with a disposable Akaunting instance and synthetic data. Static analysis currently covers the framework-independent financial support classes; adapters that depend on the Akaunting runtime are validated by PHP lint and runtime integration.

## Installation

Install the repository contents as an Akaunting module under `modules/AkauntingReports` using the platform's normal module lifecycle.

The report registration commands accept **company-specific** expense category, item and document prefix settings. Run `php artisan list` to inspect available `akaunting-reports:install-*` commands and their options. Validate them with `--dry-run` first. Run without `--dry-run` only in the approved environment and after checking each company's mappings.

Do not use production credentials in local tests or CI. Financial reconciliation and authorization are required before deployment; a successful unit test or build is not deployment approval.
