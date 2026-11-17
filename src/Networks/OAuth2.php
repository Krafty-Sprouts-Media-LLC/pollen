<?php

/**
 * Pollen: the OAuth 2 steps networks share.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * The parts of OAuth 2's authorization-code flow every network repeats: the
 * consent address, and a token request by form with the app's keys in the
 * body or as HTTP Basic. Answers come back as plain credentials.
 *
 * @since 1.0.0
 */
final readonly class OAuth2
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    /**
     * Returns a consent address with these parameters.
     *
     * @param  array<string, string>  $params
     *
     * @since 1.0.0
     */
    public static function authorizeUrl(string $endpoint, array $params): string
    {
        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Returns the token answer for a code or a refresh token, as credentials with an absolute expiry.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $headers
     * @return array{access_token: string, refresh_token: string, expires_at: int|null, raw: array<mixed>}
     *
     * @throws ShareFailed
     *
     * @since 1.0.0
     */
    public function token(string $endpoint, array $fields, array $headers = []): array
    {
        $answer = $this->http->postForm($endpoint, $fields, ['Accept' => 'application/json', ...$headers]);
        $access = (string) ($answer['access_token'] ?? '');

        if ($access === '') {
            throw ShareFailed::reconnect(__('The network didn’t give Pollen a sign-in. Connect the channel again.'));
        }

        $expires = isset($answer['expires_in']) && is_numeric($answer['expires_in']) ? time() + (int) $answer['expires_in'] : null;

        return [
            'access_token' => $access,
            'refresh_token' => (string) ($answer['refresh_token'] ?? $fields['refresh_token'] ?? ''),
            'expires_at' => $expires,
            'raw' => $answer,
        ];
    }

    /**
     * Returns whether credentials with this expiry should be renewed now, within the next ten minutes.
     *
     * @param  array<string, mixed>  $credentials
     *
     * @since 1.0.0
     */
    public static function expiring(array $credentials, int $within = 600): bool
    {
        $at = $credentials['expires_at'] ?? null;

        return is_int($at) && $at - $within <= time();
    }
}
