<?php

/**
 * Pollen: posting to another WordPress site.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to another WordPress site through its REST API, with a user name and
 * an application password made under that user's profile: a short post with
 * the item's title, the share text and a link back to the original.
 *
 * @since 1.0.0
 */
final readonly class WordPress implements TokenNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'wordpress';
    }

    public function label(): string
    {
        return __('WordPress site');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 20000, image: Limits::IMAGE_OPTIONAL, firstComment: false, linkCard: true);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        $me = $this->http->get($this->api($channel, 'users/me?context=edit'), $this->auth($channel, trim($token)));

        if (! isset($me['id'])) {
            throw ShareFailed::reconnect(__('The WordPress site didn’t accept the user name and application password.'));
        }

        return new Connected(['password' => trim($token)], (string) $me['id'], (string) ($me['name'] ?? $channel->handle).' · '.$channel->serverHost());
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $answer = $this->http->postJson($this->api($channel, 'posts'), [
            'title' => $post->title !== '' ? $post->title : mb_substr(strtok($post->text, "\n") ?: $post->text, 0, 200),
            'content' => Html::article($post),
            'status' => $channel->option('extra') === 'draft' ? 'draft' : 'publish',
        ], $this->auth($channel, (string) ($credentials['password'] ?? '')));

        return new Sent((string) ($answer['id'] ?? ''), (string) ($answer['link'] ?? ''));
    }

    /**
     * Returns a REST address on the channel's site.
     */
    private function api(Channel $channel, string $path): string
    {
        return 'https://'.$channel->serverHost().'/wp-json/wp/v2/'.$path;
    }

    /**
     * Returns the Basic header for the user name and application password.
     *
     * @return array<string, string>
     */
    private function auth(Channel $channel, string $password): array
    {
        return ['Authorization' => 'Basic '.base64_encode(trim($channel->handle).':'.str_replace(' ', '', $password))];
    }
}
