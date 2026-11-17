<?php

/**
 * Pollen: posting to a Telegram channel or group.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to a Telegram channel or group through the Bot API, with a bot the
 * site owner made with BotFather and added to the chat as an administrator:
 * a message with its link preview, or a photo with the text as its caption.
 *
 * @since 1.0.0
 */
final readonly class Telegram implements TokenNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return __('Telegram');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 4096, image: Limits::IMAGE_OPTIONAL, firstComment: false, linkCard: false);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        $token = trim($token);

        if (preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $token) !== 1) {
            throw ShareFailed::final(__('That doesn’t look like a bot token from BotFather, such as 123456:ABC-DEF….'));
        }

        if (trim($channel->account) === '') {
            throw ShareFailed::final(__('Name the Telegram chat in Account, such as @yourchannel or its number.'));
        }

        $chat = $this->http->postJson(self::api($token, 'getChat'), ['chat_id' => trim($channel->account)]);
        $result = is_array($chat['result'] ?? null) ? $chat['result'] : [];

        if (($chat['ok'] ?? false) !== true || ! isset($result['id'])) {
            throw ShareFailed::final(__('Telegram couldn’t find that chat for this bot. Add the bot to it as an administrator.'));
        }

        $username = (string) ($result['username'] ?? '');

        return new Connected(['token' => $token], (string) $result['id'], (string) ($result['title'] ?? $username), options: ['username' => $username]);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $token = (string) ($credentials['token'] ?? '');
        $silent = str_contains(mb_strtolower($channel->option('extra')), 'silent');

        if ($post->imageUrl !== '') {
            $answer = $this->http->postJson(self::api($token, 'sendPhoto'), [
                'chat_id' => $channel->account,
                'photo' => $post->imageUrl,
                'caption' => mb_substr($post->text, 0, 1024),
                'disable_notification' => $silent,
            ]);
        } else {
            $answer = $this->http->postJson(self::api($token, 'sendMessage'), [
                'chat_id' => $channel->account,
                'text' => $post->text,
                'link_preview_options' => ['is_disabled' => $post->link === ''],
                'disable_notification' => $silent,
            ]);
        }

        if (($answer['ok'] ?? false) !== true) {
            throw ShareFailed::final((string) ($answer['description'] ?? __('Telegram refused the message.')));
        }

        $id = (string) ($answer['result']['message_id'] ?? '');
        $username = $channel->option('username');

        return new Sent($id, $username !== '' ? 'https://t.me/'.$username.'/'.$id : '');
    }

    /**
     * Returns a Bot API method's address for this token.
     */
    private static function api(string $token, string $method): string
    {
        return 'https://api.telegram.org/bot'.$token.'/'.$method;
    }
}
