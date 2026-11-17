<?php

/**
 * Pollen: posting to X.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to an X account through the X API's v2 posts endpoint, signed in with
 * OAuth 2 and PKCE through the site owner's own app. Access lasts two hours,
 * so Pollen renews it with the refresh token before each post that needs it.
 * The first comment is a reply to the post.
 *
 * @since 1.0.0
 */
final readonly class X implements OAuthNetwork
{
    private const API = 'https://api.x.com/2';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'x';
    }

    public function label(): string
    {
        return __('X');
    }

    public function app(): string
    {
        return 'x';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 280, linkWeight: 23, image: Limits::IMAGE_NONE, firstComment: true, linkCard: false);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return OAuth2::authorizeUrl('https://x.com/i/oauth2/authorize', [
            'response_type' => 'code',
            'client_id' => $app->id,
            'redirect_uri' => $redirect,
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $token = (new OAuth2($this->http))->token(self::API.'/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
            'code_verifier' => $verifier,
            'client_id' => $app->id,
        ], ['Authorization' => $app->basic()]);

        $me = $this->http->get(self::API.'/users/me', ['Authorization' => 'Bearer '.$token['access_token']]);
        $id = (string) ($me['data']['id'] ?? '');
        $username = (string) ($me['data']['username'] ?? '');

        return new Connected(self::credentials($token), $id, $username !== '' ? '@'.$username : $id, $token['expires_at'], options: ['username' => $username]);
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
        ], ['Authorization' => $app->basic()]);

        return new Connected(self::credentials($token), $channel->account, $channel->accountName, $token['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $headers = ['Authorization' => 'Bearer '.(string) ($credentials['access_token'] ?? '')];
        $answer = $this->http->postJson(self::API.'/tweets', ['text' => $post->text], $headers);
        $id = (string) ($answer['data']['id'] ?? '');

        if ($id === '') {
            throw ShareFailed::final(__('X didn’t say which post it made.'));
        }

        $note = '';

        if ($post->firstComment !== '') {
            try {
                $this->http->postJson(self::API.'/tweets', ['text' => $post->firstComment, 'reply' => ['in_reply_to_tweet_id' => $id]], $headers);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        $username = $channel->option('username');

        return new Sent($id, 'https://x.com/'.($username !== '' ? $username : 'i/web').'/status/'.$id, $note);
    }

    /**
     * Returns the credentials to seal from a token answer.
     *
     * @param  array{access_token: string, refresh_token: string, expires_at: int|null}  $token
     * @return array<string, mixed>
     */
    private static function credentials(array $token): array
    {
        return ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']];
    }
}
