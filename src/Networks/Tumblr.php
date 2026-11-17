<?php

/**
 * Pollen: posting to a Tumblr blog.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to one of the account's Tumblr blogs through Tumblr's API with its
 * Neue Post Format: the text, a link block for the item, the image by its
 * address, and the item's terms as tags. Signs in with OAuth 2.
 *
 * @since 1.0.0
 */
final readonly class Tumblr implements OAuthNetwork
{
    private const API = 'https://api.tumblr.com/v2';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'tumblr';
    }

    public function label(): string
    {
        return __('Tumblr');
    }

    public function app(): string
    {
        return 'tumblr';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 4096, image: Limits::IMAGE_OPTIONAL, firstComment: false, linkCard: true);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return OAuth2::authorizeUrl('https://www.tumblr.com/oauth2/authorize', [
            'client_id' => $app->id,
            'response_type' => 'code',
            'scope' => 'basic write offline_access',
            'state' => $state,
            'redirect_uri' => $redirect,
        ]);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $token = (new OAuth2($this->http))->token(self::API.'/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $app->id,
            'client_secret' => $app->secret,
            'redirect_uri' => $redirect,
        ]);

        $credentials = ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']];
        $info = $this->http->get(self::API.'/user/info', ['Authorization' => 'Bearer '.$token['access_token']]);
        $choices = [];

        foreach (is_array($info['response']['user']['blogs'] ?? null) ? $info['response']['user']['blogs'] : [] as $blog) {
            if (is_array($blog) && isset($blog['name'])) {
                $choices[] = ['id' => (string) $blog['name'], 'name' => (string) ($blog['title'] ?? $blog['name'])];
            }
        }

        $chosen = Connected::pick($choices, $channel->account);

        return $chosen === null
            ? new Connected($credentials, expiresAt: $token['expires_at'], choices: $choices)
            : new Connected($credentials, $chosen['id'], $chosen['name'], $token['expires_at']);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        if (! OAuth2::expiring($credentials) || ($credentials['refresh_token'] ?? '') === '') {
            return null;
        }

        $token = (new OAuth2($this->http))->token(self::API.'/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $credentials['refresh_token'],
            'client_id' => $app->id,
            'client_secret' => $app->secret,
        ]);

        return new Connected(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']], $channel->account, $channel->accountName, $token['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $content = [['type' => 'text', 'text' => $post->text]];

        if ($post->imageUrl !== '') {
            $content[] = ['type' => 'image', 'media' => [['url' => $post->imageUrl]]];
        }

        if ($post->link !== '' && $post->linkMode === Post::LINK_CARD) {
            $content[] = array_filter(['type' => 'link', 'url' => $post->link, 'title' => $post->title, 'description' => $post->excerpt]);
        }

        preg_match_all('/#([\p{L}\p{N}_]+)/u', $post->text, $tags);
        $answer = $this->http->postJson(self::API.'/blog/'.rawurlencode($channel->account).'/posts', array_filter([
            'content' => $content,
            'tags' => implode(',', array_unique($tags[1])),
        ]), ['Authorization' => 'Bearer '.(string) ($credentials['access_token'] ?? '')]);

        $id = (string) ($answer['response']['id'] ?? $answer['response']['id_string'] ?? '');

        return new Sent($id, $id !== '' ? 'https://www.tumblr.com/'.$channel->account.'/'.$id : '');
    }
}
