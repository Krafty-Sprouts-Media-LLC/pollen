<?php

/**
 * Pollen: posting to a VK wall.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to a VK community's or person's wall through the VK API's wall.post,
 * with an access token that may post to walls. A community's wall is named by
 * its ID with a minus sign, and the post goes as the community.
 *
 * @since 1.0.0
 */
final readonly class Vk implements TokenNetwork
{
    private const API = 'https://api.vk.com/method/';

    private const VERSION = '5.199';

    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'vk';
    }

    public function label(): string
    {
        return __('VK');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 15000, image: Limits::IMAGE_NONE, firstComment: false, linkCard: true);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        if (preg_match('/^-?\d+$/', trim($channel->account)) !== 1) {
            throw ShareFailed::final(__('Name the wall in Account by its number: a community’s with a minus sign, such as -123456.'));
        }

        $this->call('users.get', ['access_token' => trim($token)]);

        return new Connected(['token' => trim($token)], trim($channel->account), trim($channel->account));
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $owner = $channel->account;
        $answer = $this->call('wall.post', array_filter([
            'owner_id' => $owner,
            'from_group' => str_starts_with($owner, '-') ? '1' : '',
            'message' => $post->text,
            'attachments' => $post->linkMode === Post::LINK_CARD ? $post->link : '',
            'access_token' => (string) ($credentials['token'] ?? ''),
        ], fn (string $v): bool => $v !== ''));

        $id = (string) ($answer['response']['post_id'] ?? '');

        return new Sent($id, $id !== '' ? 'https://vk.com/wall'.$owner.'_'.$id : '');
    }

    /**
     * Calls a VK method and returns its answer, sorting VK's own errors, which come with status 200.
     *
     * @param  array<string, string>  $fields
     * @return array<mixed>
     *
     * @throws ShareFailed
     */
    private function call(string $method, array $fields): array
    {
        $answer = $this->http->postForm(self::API.$method, [...$fields, 'v' => self::VERSION]);
        $error = $answer['error'] ?? null;

        if (is_array($error)) {
            $code = (int) ($error['error_code'] ?? 0);
            $said = (string) ($error['error_msg'] ?? __('VK refused it.'));

            throw match (true) {
                in_array($code, [5, 27, 28], true) => ShareFailed::reconnect($said),
                in_array($code, [6, 9, 10], true) => ShareFailed::retry($said),
                default => ShareFailed::final($said),
            };
        }

        return $answer;
    }
}
