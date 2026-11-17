<?php

/**
 * Pollen: new record IDs.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use Illuminate\Support\Str;

/**
 * Makes the IDs of Pollen's records: lower-case ULIDs, sortable by when they
 * were made, as Teme's own items have.
 *
 * @since 1.0.0
 */
final class Ids
{
    /**
     * Returns a new lower-case ULID.
     *
     * @since 1.0.0
     */
    public static function make(): string
    {
        return strtolower((string) Str::ulid());
    }

    /**
     * Returns a random key of 40 letters and digits, for addresses that must not be guessed.
     *
     * @since 1.0.0
     */
    public static function key(bool $lower = false): string
    {
        $alphabet = $lower ? 'abcdefghijklmnopqrstuvwxyz0123456789' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $key = '';

        for ($i = 0; $i < 40; $i++) {
            $key .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $key;
    }
}
