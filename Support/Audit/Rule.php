<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

/** Domain rules must not query databases, call APIs or write accounting data. */
interface Rule
{
    public function id(): string;

    public function version(): int;

    /** @return list<string> */
    public function sources(): array;

    /** @return list<Occurrence> Empty means no applicable finding. */
    public function inspect(Snapshot $snapshot): array;
}
