<?php

/**
 * Pollen: pinning to a Pinterest board.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Makes a Pin on one of the account's boards through Pinterest's API v5: the
 * image from its address, the item's title, the share text as description
 * and the link the Pin leads to. Access lasts 30 days and is renewed.
 *
 * @since 1.0.0
 */
final readonly class Pinterest implements OAuthNetwork
{
    private const API = 'https://api.pinterest.com/v5';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'pinterest';
    }

    public function label(): string
    {
        return __('Pinterest board');
    }

    public function app(): string
    {
        return 'pinterest';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 500, image: Limits::IMAGE_REQUIRED, firstComment: false, linkCard: false, imageSize: [1000, 1500], titleLength: 100);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return OAuth2::authorizeUrl('https://www.pinterest.com/oauth/', [
            'client_id' => $app->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'boards:read,pins:read,pins:write,user_accounts:read',
            'state' => $state,
        ]);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $token = (new OAuth2($this->http))->token(self::API.'/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
        ], ['Authorization' => $app->basic()]);

        $credentials = ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']];
        $boards = $this->http->get(self::API.'/boards?page_size=100', ['Authorization' => 'Bearer '.$token['access_token']]);
        $choices = [];

        foreach (is_array($boards['items'] ?? null) ? $boards['items'] : [] as $board) {
            if (is_array($board) && isset($board['id'])) {
                $choices[] = ['id' => (string) $board['id'], 'name' => (string) ($board['name'] ?? $board['id'])];
            }
        }

        $chosen = Connected::pick($choices, $channel->account);

        return $chosen === null
            ? new Connected($credentials, expiresAt: $token['expires_at'], choices: $choices)
            : new Connected($credentials, $chosen['id'], $chosen['name'], $token['expires_at']);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        if (! OAuth2::expiring($credentials, 3 * 86400) || ($credentials['refresh_token'] ?? '') === '') {
            return null;
        }

        $token = (new OAuth2($this->http))->token(self::API.'/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $credentials['refresh_token'],
        ], ['Authorization' => $app->basic()]);

        return new Connected(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'expires_at' => $token['expires_at']], $channel->account, $channel->accountName, $token['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        if ($post->imageUrl === '') {
            throw ShareFailed::final(__('Pinterest needs an image, and this share has none.'));
        }

        $pin = array_filter([
            'board_id' => $channel->account,
            'title' => mb_substr($post->title, 0, 100),
            'description' => mb_substr($post->text, 0, 500),
            'link' => $post->link,
            'alt_text' => mb_substr($post->title, 0, 500),
            'media_source' => ['source_type' => 'image_url', 'url' => $post->imageUrl],
        ], fn (mixed $v): bool => $v !== '');

        $answer = $this->http->postJson(self::API.'/pins', $pin, ['Authorization' => 'Bearer '.(string) ($credentials['access_token'] ?? '')]);
        $id = (string) ($answer['id'] ?? '');

        if ($id === '') {
            throw ShareFailed::final(__('Pinterest didn’t say which Pin it made.'));
        }

        return new Sent($id, 'https://www.pinterest.com/pin/'.$id.'/');
    }
}
