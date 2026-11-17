<?php

/**
 * Pollen: signing in with a Meta app for Facebook Pages and Instagram.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks\Meta;

use KraftySprouts\Pollen\Networks\AppKeys;
use KraftySprouts\Pollen\Networks\Http;
use KraftySprouts\Pollen\Networks\ShareFailed;

/**
 * Facebook Login with the site owner's Meta app: the consent address, the
 * code's exchange for a long-lived person token, and the Pages that token
 * manages, each with its own Page token and linked Instagram account.
 *
 * @since 1.0.0
 */
final readonly class MetaSignIn
{
    /** The Graph API version Pollen speaks. */
    public const VERSION = 'v23.0';

    /** The Graph API's host. */
    public const GRAPH = 'https://graph.facebook.com/'.self::VERSION;

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    /**
     * Returns the Facebook Login address asking for these permissions.
     *
     * @param  list<string>  $scopes
     *
     * @since 1.0.0
     */
    public function authorizeUrl(AppKeys $app, string $redirect, string $state, array $scopes): string
    {
        return 'https://www.facebook.com/'.self::VERSION.'/dialog/oauth?'.http_build_query([
            'client_id' => $app->id,
            'redirect_uri' => $redirect,
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Returns a long-lived person token for the code.
     *
     * @throws ShareFailed
     *
     * @since 1.0.0
     */
    public function personToken(AppKeys $app, string $code, string $redirect): string
    {
        $short = $this->http->get(self::GRAPH.'/oauth/access_token?'.http_build_query([
            'client_id' => $app->id,
            'client_secret' => $app->secret,
            'redirect_uri' => $redirect,
            'code' => $code,
        ]));

        $long = $this->http->get(self::GRAPH.'/oauth/access_token?'.http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $app->id,
            'client_secret' => $app->secret,
            'fb_exchange_token' => (string) ($short['access_token'] ?? ''),
        ]));

        $token = (string) ($long['access_token'] ?? $short['access_token'] ?? '');

        if ($token === '') {
            throw ShareFailed::final(__('Facebook didn’t give Pollen a sign-in. Try connecting again.'));
        }

        return $token;
    }

    /**
     * Returns the Pages the person token manages, with their Page tokens and linked Instagram accounts.
     *
     * @return list<array{id: string, name: string, token: string, instagram: string, instagramName: string}>
     *
     * @throws ShareFailed
     *
     * @since 1.0.0
     */
    public function pages(string $personToken): array
    {
        $answer = $this->http->get(self::GRAPH.'/me/accounts?'.http_build_query([
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            'limit' => '100',
            'access_token' => $personToken,
        ]));

        $pages = [];

        foreach (is_array($answer['data'] ?? null) ? $answer['data'] : [] as $page) {
            if (! is_array($page) || ! isset($page['id'], $page['access_token'])) {
                continue;
            }

            $pages[] = [
                'id' => (string) $page['id'],
                'name' => (string) ($page['name'] ?? $page['id']),
                'token' => (string) $page['access_token'],
                'instagram' => (string) ($page['instagram_business_account']['id'] ?? ''),
                'instagramName' => (string) ($page['instagram_business_account']['username'] ?? ''),
            ];
        }

        return $pages;
    }
}
