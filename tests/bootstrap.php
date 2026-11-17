<?php

/**
 * Pollen: loads Pollen's classes for tests that use them before the kit's plugin() has.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'KraftySprouts\\Pollen\\';

    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/../src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
});
