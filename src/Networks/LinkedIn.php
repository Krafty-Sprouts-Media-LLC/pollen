<?php

/**
 * Pollen: posting to LinkedIn, as a person or a page.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to a LinkedIn member's feed, or a LinkedIn page the member
 * administers, through LinkedIn's versioned Posts API, with the link as an
 * article card. A page needs the Community Management API on the app.
 *
 * @since 1.0.0
 */
final readonly class LinkedIn implements OAuthNetwork
{
    private const API = 'https://api.linkedin.com';

    /** The Posts API version Pollen speaks, as LinkedIn-Version names it. */
    private const VERSION = '202510';

    /**
     * @param  bool  $page  true for a page (an organization), false for the member's own profile
     *
     * @since 1.0.0
     */
    public function __construct(private Http $http, private bool $page = false) {}

    public function key(): string
    {
        return $this->page ? 'linkedin-page' : 'linkedin';
    }

    public function label(): string
    {
        return $this->page ? __('LinkedIn page') : __('LinkedIn profile');
    }

    public function app(): string
    {
        return 'linkedin';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 3000, image: Limits::IMAGE_NONE, firstComment: true, linkCard: true);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        $scopes = ['openid', 'profile', 'w_member_social'];

        if ($this->page) {
            $scopes = [...$scopes, 'w_organization_social', 'r_organization_admin'];
        }

        return OAuth2::authorizeUrl('https://www.linkedin.com/oauth/v2/authorization', [
            'response_type' => 'code',
            'client_id' => $app->id,
            'redirect_uri' => $redirect,
            'state' => $state,
            'scope' => implode(' ', $scopes),
        ]);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $token = (new OAuth2($this->http))->token('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
            'client_id' => $app->id,
            'client_secret' => $app->secret,
        ]);

        $credentials = ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']];
        $me = $this->http->get(self::API.'/v2/userinfo', ['Authorization' => 'Bearer '.$token['access_token']]);
        $member = (string) ($me['sub'] ?? '');

        if (! $this->page) {
            return new Connected($credentials, 'urn:li:person:'.$member, (string) ($me['name'] ?? $member), $token['expires_at']);
        }

        $acls = $this->http->get(self::API.'/rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED', $this->headers($token['access_token']));
        $choices = [];

        foreach (is_array($acls['elements'] ?? null) ? $acls['elements'] : [] as $acl) {
            $urn = is_array($acl) ? (string) ($acl['organization'] ?? $acl['organizationTarget'] ?? '') : '';

            if ($urn !== '') {
                $choices[] = ['id' => $urn, 'name' => $urn];
            }
        }

        $wanted = trim($channel->account);
        $wanted = $wanted !== '' && ctype_digit($wanted) ? 'urn:li:organization:'.$wanted : $wanted;
        $chosen = Connected::pick($choices, $wanted);

        return $chosen === null
            ? new Connected($credentials, expiresAt: $token['expires_at'], choices: $choices)
            : new Connected($credentials, $chosen['id'], $chosen['name'], $token['expires_at']);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        if (! OAuth2::expiring($credentials, 7 * 86400) || ($credentials['refresh_token'] ?? '') === '') {
            return null;
        }

        $token = (new OAuth2($this->http))->token('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $credentials['refresh_token'],
            'client_id' => $app->id,
            'client_secret' => $app->secret,
        ]);

        return new Connected(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']], $channel->account, $channel->accountName, $token['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $token = (string) ($credentials['access_token'] ?? '');
        $body = [
            'author' => $channel->account,
            'commentary' => self::escape($post->text),
            'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        if ($post->link !== '' && $post->linkMode === Post::LINK_CARD) {
            $body['content'] = ['article' => array_filter(['source' => $post->link, 'title' => mb_substr($post->title, 0, 400), 'description' => mb_substr($post->excerpt, 0, 4086)], fn (string $v): bool => $v !== '')];
        }

        $answer = $this->http->postJsonAnswer(self::API.'/rest/posts', $body, $this->headers($token));
        $header = $answer->headers['x-restli-id'] ?? $answer->headers['X-RestLi-Id'] ?? '';
        $urn = is_array($header) ? (string) ($header[0] ?? '') : (string) $header;

        if ($urn === '') {
            throw ShareFailed::final(__('LinkedIn didn’t say which post it made.'));
        }

        $note = '';

        if ($post->firstComment !== '') {
            try {
                $this->http->postJson(self::API.'/rest/socialActions/'.rawurlencode($urn).'/comments', [
                    'actor' => $channel->account,
                    'object' => $urn,
                    'message' => ['text' => self::escape($post->firstComment)],
                ], $this->headers($token));
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        return new Sent($urn, 'https://www.linkedin.com/feed/update/'.$urn.'/', $note);
    }

    /**
     * Returns the headers LinkedIn's versioned API needs.
     *
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'LinkedIn-Version' => self::VERSION, 'X-Restli-Protocol-Version' => '2.0.0'];
    }

    /**
     * Returns text with the characters LinkedIn's commentary format reserves escaped, so they show as written.
     *
     * @since 1.0.0
     */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\|{}@\[\]()<>#*_~])/u', '\\\\$1', $text);
    }
}
