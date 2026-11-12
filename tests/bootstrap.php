<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// The unit suite runs without a Laravel installation. This boundary adapter
// reads only the bundled English catalogue, never accounting services.
if (!function_exists('trans')) {
    function trans(string $key, array $replace = []): string
    {
        $catalogue = require dirname(__DIR__) . '/Resources/lang/en/ui.php';
        $prefix = 'akaunting-reports::ui.';
        if (!str_starts_with($key, $prefix)) {
            return $key;
        }
        $path = substr($key, strlen($prefix));
        $value = $catalogue;
        foreach (explode('.', $path) as $part) {
            $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $key;
        }
        return is_string($value) ? strtr($value, $replace) : $key;
    }
}
