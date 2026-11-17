<?php

/**
 * Pollen: PKCE for OAuth 2 sign-ins.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Connect;

/**
 * Makes the proof key a sign-in sends with its code (RFC 7636): a random
 * verifier kept by Pollen, and the S256 challenge the network is shown.
 *
 * @since 1.0.0
 */
final class Pkce
{
    /**
     * Returns a new random verifier of 64 URL-safe characters.
     *
     * @since 1.0.0
     */
    public static function verifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    /**
     * Returns the S256 challenge for a verifier.
     *
     * @since 1.0.0
     */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
