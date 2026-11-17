<?php

/**
 * Pollen: posting to Threads.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to a Threads profile through the Threads API: a text post with a
 * link attachment, or an image post, made as a container then published.
 * Access lasts 60 days and is renewed; the first comment is a reply.
 *
 * @since 1.0.0
 */
final readonly class Threads implements OAuthNetwork
{
    private const API = 'https://graph.threads.net';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'threads';
    }

    public function label(): string
    {
        return __('Threads');
    }

    public function app(): string
    {
        return 'threads';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 500, image: Limits::IMAGE_OPTIONAL, firstComment: true, linkCard: true, imageSize: [1080, 1350]);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return OAuth2::authorizeUrl('https://threads.net/oauth/authorize', [
            'client_id' => $app->id,
            'redirect_uri' => $redirect,
            'scope' => 'threads_basic,threads_content_publish',
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $short = (new OAuth2($this->http))->token(self::API.'/oauth/access_token', [
            'client_id' => $app->id,
            'client_secret' => $app->secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirect,
            'code' => $code,
        ]);

        $long = $this->http->get(self::API.'/access_token?'.http_build_query([
            'grant_type' => 'th_exchange_token',
            'client_secret' => $app->secret,
            'access_token' => $short['access_token'],
        ]));

        $token = (string) ($long['access_token'] ?? $short['access_token']);
        $expires = isset($long['expires_in']) ? time() + (int) $long['expires_in'] : $short['expires_at'];
        $me = $this->http->get(self::API.'/v1.0/me?'.http_build_query(['fields' => 'id,username', 'access_token' => $token]));
        $username = (string) ($me['username'] ?? '');

        return new Connected(['access_token' => $token, 'expires_at' => $expires], (string) ($me['id'] ?? ''), $username !== '' ? '@'.$username : '', $expires, options: ['username' => $username]);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        if (! OAuth2::expiring($credentials, 10 * 86400)) {
            return null;
        }

        $answer = $this->http->get(self::API.'/refresh_access_token?'.http_build_query([
            'grant_type' => 'th_refresh_token',
            'access_token' => (string) ($credentials['access_token'] ?? ''),
        ]));

        $token = (string) ($answer['access_token'] ?? '');

        if ($token === '') {
            throw ShareFailed::reconnect(__('Threads didn’t renew the sign-in. Connect the channel again.'));
        }

        $expires = time() + (int) ($answer['expires_in'] ?? 5184000);

        return new Connected(['access_token' => $token, 'expires_at' => $expires], $channel->account, $channel->accountName, $expires);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $token = (string) ($credentials['access_token'] ?? '');
        $id = $this->post($channel->account, $token, $post->text, $post->imageUrl, $post->linkMode === Post::LINK_CARD ? $post->link : '');
        $note = '';

        if ($post->firstComment !== '') {
            try {
                $this->post($channel->account, $token, $post->firstComment, '', '', $id);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        $username = $channel->option('username');

        return new Sent($id, $username !== '' ? 'https://www.threads.net/@'.$username.'/post/'.$id : '', $note);
    }

    /**
     * Makes and publishes one post, returning its ID.
     *
     * @throws ShareFailed
     */
    private function post(string $user, string $token, string $text, string $image, string $link, string $replyTo = ''): string
    {
        $fields = array_filter([
            'media_type' => $image !== '' ? 'IMAGE' : 'TEXT',
            'text' => $text,
            'image_url' => $image,
            'link_attachment' => $image === '' ? $link : '',
            'reply_to_id' => $replyTo,
            'access_token' => $token,
        ], fn (string $v): bool => $v !== '');

        $container = (string) ($this->http->postForm(self::API.'/v1.0/'.rawurlencode($user).'/threads', $fields)['id'] ?? '');

        if ($container === '') {
            throw ShareFailed::final(__('Threads didn’t take the post.'));
        }

        $id = (string) ($this->http->postForm(self::API.'/v1.0/'.rawurlencode($user).'/threads_publish', ['creation_id' => $container, 'access_token' => $token])['id'] ?? '');

        if ($id === '') {
            throw ShareFailed::retry(__('Threads was still preparing the post. Pollen will try again.'));
        }

        return $id;
    }
}
