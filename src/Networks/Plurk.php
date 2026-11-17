<?php

/**
 * Pollen: posting to Plurk.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Connect\OAuth1;

/**
 * Posts a plurk through Plurk's API 2.0, signed with OAuth 1.0a: the site
 * owner's app key and secret from Settings, and the account's access token
 * and secret, which Plurk's app console gives for the app, typed as
 * "token:secret" into the channel.
 *
 * @since 1.0.0
 */
final readonly class Plurk implements TokenNetwork
{
    private const API = 'https://www.plurk.com/APP/';

    /**
     * @param  (callable(): AppKeys)|null  $app  the app's keys, read when needed
     *
     * @since 1.0.0
     */
    public function __construct(private Http $http, private mixed $app = null) {}

    public function key(): string
    {
        return 'plurk';
    }

    public function label(): string
    {
        return __('Plurk');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 360, image: Limits::IMAGE_NONE, firstComment: false, linkCard: false);
    }

    /**
     * Returns a copy that reads the app's keys from Pollen's settings.
     *
     * @param  callable(): AppKeys  $app
     *
     * @since 1.0.0
     */
    public function withApp(callable $app): self
    {
        return new self($this->http, $app);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        [$key, $secret] = self::split($token);
        $me = $this->signed('Users/me', [], $key, $secret);

        return new Connected(['token' => $key.':'.$secret], (string) ($me['id'] ?? ''), (string) ($me['nick_name'] ?? $me['display_name'] ?? ''));
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        [$key, $secret] = self::split((string) ($credentials['token'] ?? ''));
        $answer = $this->signed('Timeline/plurkAdd', ['content' => $post->text, 'qualifier' => $channel->option('extra') !== '' ? $channel->option('extra') : 'shares'], $key, $secret);
        $id = (string) ($answer['plurk_id'] ?? '');

        return new Sent($id, $id !== '' ? 'https://www.plurk.com/p/'.base_convert($id, 10, 36) : '');
    }

    /**
     * Sends a signed form request.
     *
     * @param  array<string, string>  $fields
     * @return array<mixed>
     *
     * @throws ShareFailed
     */
    private function signed(string $method, array $fields, string $token, string $tokenSecret): array
    {
        $app = $this->app === null ? new AppKeys('', '') : ($this->app)();

        if (! $app->ready()) {
            throw ShareFailed::reconnect(__('Add your Plurk app key and secret in Settings › Pollen.'));
        }

        $url = self::API.$method;
        $header = OAuth1::header('POST', $url, $fields, $app->id, $app->secret, $token, $tokenSecret);

        return $this->http->postForm($url, $fields, ['Authorization' => $header]);
    }

    /**
     * Returns the token and its secret from "token:secret".
     *
     * @return array{0: string, 1: string}
     *
     * @throws ShareFailed when it isn't in that form
     */
    private static function split(string $token): array
    {
        $parts = explode(':', trim($token), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ShareFailed::reconnect(__('Give Plurk’s access token and its secret as token:secret.'));
        }

        return [$parts[0], $parts[1]];
    }
}
