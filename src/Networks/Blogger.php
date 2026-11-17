<?php

/**
 * Pollen: posting to a Blogger blog.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Networks\Google\GoogleSignIn;

/**
 * Posts to one of the account's Blogger blogs through the Blogger API v3: a
 * short post with the item's title, the share text, its image, and a link
 * back to the original, so the original stays the one search engines rank.
 *
 * @since 1.0.0
 */
final readonly class Blogger implements OAuthNetwork
{
    private const API = 'https://www.googleapis.com/blogger/v3';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'blogger';
    }

    public function label(): string
    {
        return __('Blogger');
    }

    public function app(): string
    {
        return 'google';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 20000, image: Limits::IMAGE_OPTIONAL, firstComment: false, linkCard: true);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return GoogleSignIn::authorizeUrl($app, $redirect, $state, $challenge, 'https://www.googleapis.com/auth/blogger');
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $credentials = (new GoogleSignIn($this->http))->exchange($app, $code, $verifier, $redirect);
        $blogs = $this->http->get(self::API.'/users/self/blogs', ['Authorization' => 'Bearer '.$credentials['access_token']]);
        $choices = [];

        foreach (is_array($blogs['items'] ?? null) ? $blogs['items'] : [] as $blog) {
            $choices[] = ['id' => (string) $blog['id'], 'name' => (string) ($blog['name'] ?? $blog['id'])];
        }

        $chosen = Connected::pick($choices, $channel->account);

        return $chosen === null
            ? new Connected($credentials, expiresAt: $credentials['expires_at'], choices: $choices)
            : new Connected($credentials, $chosen['id'], $chosen['name'], $credentials['expires_at']);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        $renewed = (new GoogleSignIn($this->http))->refresh($app, $credentials);

        return $renewed === null ? null : new Connected($renewed, $channel->account, $channel->accountName, $renewed['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $answer = $this->http->postJson(
            self::API.'/blogs/'.rawurlencode($channel->account).'/posts/'.($channel->option('extra') === 'draft' ? '?isDraft=true' : ''),
            ['title' => $post->title !== '' ? $post->title : mb_substr(strtok($post->text, "\n") ?: $post->text, 0, 200), 'content' => Html::article($post)],
            ['Authorization' => 'Bearer '.(string) ($credentials['access_token'] ?? '')],
        );

        return new Sent((string) ($answer['id'] ?? ''), (string) ($answer['url'] ?? ''));
    }
}
