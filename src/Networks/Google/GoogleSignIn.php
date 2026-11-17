<?php

/**
 * Pollen: signing in with a Google OAuth client.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks\Google;

use KraftySprouts\Pollen\Networks\AppKeys;
use KraftySprouts\Pollen\Networks\Http;
use KraftySprouts\Pollen\Networks\OAuth2;

/**
 * Google's OAuth 2 with PKCE and offline access, for Google Business Profile
 * and Blogger: the consent address for a scope, the code's exchange, and the
 * hourly renewal with the refresh token.
 *
 * @since 1.0.0
 */
final readonly class GoogleSignIn
{
    private const TOKEN = 'https://oauth2.googleapis.com/token';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    /**
     * Returns the consent address for a scope.
     *
     * @since 1.0.0
     */
    public static function authorizeUrl(AppKeys $app, string $redirect, string $state, string $challenge, string $scope): string
    {
        return OAuth2::authorizeUrl('https://accounts.google.com/o/oauth2/v2/auth', [
            'client_id' => $app->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Returns the credentials for a code.
     *
     * @return array{access_token: string, refresh_token: string, expires_at: int|null}
     *
     * @since 1.0.0
     */
    public function exchange(AppKeys $app, string $code, string $verifier, string $redirect): array
    {
        $token = (new OAuth2($this->http))->token(self::TOKEN, [
            'code' => $code,
            'client_id' => $app->id,
            'client_secret' => $app->secret,
            'redirect_uri' => $redirect,
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);

        return ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']];
    }

    /**
     * Returns renewed credentials when they expire within ten minutes, or null.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{access_token: string, refresh_token: string, expires_at: int|null}|null
     *
     * @since 1.0.0
     */
    public function refresh(AppKeys $app, array $credentials): ?array
    {
        if (! OAuth2::expiring($credentials) || ($credentials['refresh_token'] ?? '') === '') {
            return null;
        }

        $token = (new OAuth2($this->http))->token(self::TOKEN, [
            'client_id' => $app->id,
            'client_secret' => $app->secret,
            'refresh_token' => (string) $credentials['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        return ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']];
    }
}
