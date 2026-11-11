<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AkauntingReports\Console\InstallCapitalSocial;

final class Main extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'akaunting-reports');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'akaunting-reports');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCapitalSocial::class]);
        }
    }
}
