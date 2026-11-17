<?php

/**
 * Pollen: posting to a Mastodon account on any server.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts a status to an account on any Mastodon server, with an access token
 * from the server's Development settings that may write statuses. The server
 * draws the link's preview card itself. Repeated sends of one share carry the
 * same idempotency key, so the server never posts it twice.
 *
 * @since 1.0.0
 */
final readonly class Mastodon implements TokenNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'mastodon';
    }

    public function label(): string
    {
        return __('Mastodon');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 500, linkWeight: 23, image: Limits::IMAGE_NONE, firstComment: true, linkCard: false);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        if ($channel->serverHost() === '') {
            throw ShareFailed::final(__('Give the Mastodon server’s address, such as https://mastodon.social.'));
        }

        $me = $this->http->get($this->api($channel, 'accounts/verify_credentials'), ['Authorization' => 'Bearer '.trim($token)]);

        if (! isset($me['id'])) {
            throw ShareFailed::reconnect(__('The Mastodon server didn’t accept the token.'));
        }

        return new Connected(['token' => trim($token)], (string) $me['id'], '@'.(string) ($me['acct'] ?? $me['username'] ?? '').'@'.$channel->serverHost());
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $headers = ['Authorization' => 'Bearer '.(string) ($credentials['token'] ?? '')];
        $visibility = in_array($channel->option('extra'), ['unlisted', 'private'], true) ? $channel->option('extra') : 'public';
        $status = array_filter(['status' => $post->text, 'visibility' => $visibility, 'language' => substr($post->language, 0, 2)], fn (string $v): bool => $v !== '');

        $answer = $this->http->postJson($this->api($channel, 'statuses'), $status, [...$headers, 'Idempotency-Key' => $post->key]);
        $id = (string) ($answer['id'] ?? '');

        if ($id === '') {
            throw ShareFailed::final(__('The Mastodon server didn’t say which post it made.'));
        }

        $note = '';

        if ($post->firstComment !== '') {
            try {
                $this->http->postJson($this->api($channel, 'statuses'), ['status' => $post->firstComment, 'in_reply_to_id' => $id, 'visibility' => $visibility], [...$headers, 'Idempotency-Key' => $post->key.'-c']);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        return new Sent($id, (string) ($answer['url'] ?? ''), $note);
    }

    /**
     * Returns an API method's address on the channel's server.
     */
    private function api(Channel $channel, string $method): string
    {
        return 'https://'.$channel->serverHost().'/api/v1/'.$method;
    }
}
