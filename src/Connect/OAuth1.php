<?php

/**
 * Pollen: OAuth 1.0a request signing.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Connect;

/**
 * Signs requests with OAuth 1.0a and HMAC-SHA1, as RFC 5849 describes, for
 * the networks that still use it with an account's own keys: X and Tumblr.
 *
 * @since 1.0.0
 */
final class OAuth1
{
    /**
     * Returns the Authorization header for a request whose form or query parameters are $params.
     *
     * @param  array<string, string>  $params  query and form parameters, never a JSON body's
     *
     * @since 1.0.0
     */
    public static function header(
        string $method,
        string $url,
        array $params,
        string $consumerKey,
        string $consumerSecret,
        string $token,
        string $tokenSecret,
        ?string $nonce = null,
        ?int $timestamp = null,
    ): string {
        $oauth = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => $nonce ?? bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) ($timestamp ?? time()),
            'oauth_token' => $token,
            'oauth_version' => '1.0',
        ];

        $oauth['oauth_signature'] = self::signature($method, $url, [...$params, ...$oauth], $consumerSecret, $tokenSecret);

        $parts = [];

        foreach ($oauth as $key => $value) {
            $parts[] = rawurlencode($key).'="'.rawurlencode($value).'"';
        }

        return 'OAuth '.implode(', ', $parts);
    }

    /**
     * Returns the base64 HMAC-SHA1 signature of the request.
     *
     * @param  array<string, string>  $params
     *
     * @since 1.0.0
     */
    public static function signature(string $method, string $url, array $params, string $consumerSecret, string $tokenSecret): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = [rawurlencode((string) $key), rawurlencode($value)];
        }

        usort($pairs, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $normalised = implode('&', array_map(fn (array $p): string => $p[0].'='.$p[1], $pairs));
        $base = strtoupper($method).'&'.rawurlencode(self::baseUrl($url)).'&'.rawurlencode($normalised);

        return base64_encode(hash_hmac('sha1', $base, rawurlencode($consumerSecret).'&'.rawurlencode($tokenSecret), true));
    }

    /**
     * Returns the address without its query or fragment, scheme and host in lower case.
     */
    private static function baseUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host'] ?? '');
        $port = isset($parts['port']) && ! in_array([$scheme, $parts['port']], [['http', 80], ['https', 443]], true) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.($parts['path'] ?? '/');
    }
}
