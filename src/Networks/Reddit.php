<?php

/**
 * Pollen: posting to a subreddit.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Submits a link post, or a text post for a message of its own, to a
 * subreddit through Reddit's OAuth API, signed in through the site owner's
 * web app with a permanent refresh token. Reddit asks every client to name
 * itself in its User-Agent. The first comment is a reply to the post.
 *
 * @since 1.0.0
 */
final readonly class Reddit implements OAuthNetwork
{
    private const API = 'https://oauth.reddit.com';

    private const AGENT = 'web:pollen-for-teme:1.0.0';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'reddit';
    }

    public function label(): string
    {
        return __('Reddit');
    }

    public function app(): string
    {
        return 'reddit';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 40000, image: Limits::IMAGE_NONE, firstComment: true, linkCard: true, titleLength: 300);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return OAuth2::authorizeUrl('https://www.reddit.com/api/v1/authorize', [
            'client_id' => $app->id,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $redirect,
            'duration' => 'permanent',
            'scope' => 'identity submit',
        ]);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $token = (new OAuth2($this->http))->token('https://www.reddit.com/api/v1/access_token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
        ], ['Authorization' => $app->basic(), 'User-Agent' => self::AGENT]);

        $me = $this->http->get(self::API.'/api/v1/me', ['Authorization' => 'Bearer '.$token['access_token'], 'User-Agent' => self::AGENT]);
        $subreddit = preg_replace('#^/?r/#i', '', trim($channel->account)) ?? '';

        if ($subreddit === '') {
            throw ShareFailed::final(__('Name the subreddit in Account, such as r/gardening, then connect again.'));
        }

        return new Connected(
            ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']],
            $subreddit,
            'r/'.$subreddit.' · u/'.(string) ($me['name'] ?? ''),
            $token['expires_at'],
        );
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        if (! OAuth2::expiring($credentials) || ($credentials['refresh_token'] ?? '') === '') {
            return null;
        }

        $token = (new OAuth2($this->http))->token('https://www.reddit.com/api/v1/access_token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $credentials['refresh_token'],
        ], ['Authorization' => $app->basic(), 'User-Agent' => self::AGENT]);

        return new Connected(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']], $channel->account, $channel->accountName, $token['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $headers = ['Authorization' => 'Bearer '.(string) ($credentials['access_token'] ?? ''), 'User-Agent' => self::AGENT];
        $title = mb_substr($post->title !== '' ? $post->title : strtok($post->text, "\n"), 0, 300);
        $fields = array_filter([
            'sr' => $channel->account,
            'kind' => $post->link !== '' ? 'link' : 'self',
            'title' => $title,
            'url' => $post->link,
            'text' => $post->link === '' ? $post->text : '',
            'flair_id' => $channel->option('extra'),
            'api_type' => 'json',
            'resubmit' => 'true',
        ], fn (string $v): bool => $v !== '');

        $answer = $this->http->postForm(self::API.'/api/submit', $fields, $headers);
        $errors = $answer['json']['errors'] ?? [];

        if (is_array($errors) && $errors !== []) {
            throw ShareFailed::final(__('Reddit refused it: :reason', ['reason' => implode(' ', array_map(fn ($e): string => is_array($e) ? (string) ($e[1] ?? '') : '', $errors))]));
        }

        $name = (string) ($answer['json']['data']['name'] ?? '');
        $url = (string) ($answer['json']['data']['url'] ?? '');

        if ($name === '') {
            throw ShareFailed::final(__('Reddit didn’t say which post it made.'));
        }

        $note = '';
        if (trim($post->firstComment) !== '') {
            try {
                $this->http->postForm(self::API.'/api/comment', ['thing_id' => $name, 'text' => $post->firstComment, 'api_type' => 'json'], $headers);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        return new Sent($name, $url, $note);
    }
}
