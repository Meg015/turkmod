<?php

declare(strict_types=1);

/**
 * Lightweight PSR-4 autoloader — replaces Composer's vendor/autoload.php.
 *
 * Registered namespaces:
 *   App\  →  includes/src/
 *
 * No production dependencies exist in vendor/ (only phpunit dev packages),
 * so the entire 326MB vendor tree is eliminated.
 */

spl_autoload_register(static function (string $class): void {
    // --- PSR-4: App\ → includes/src/ ---
    $prefix = 'App\\';
    $prefixLen = 4;
    if (str_starts_with($class, $prefix)) {
        $relativeClass = substr($class, $prefixLen);
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }

});
