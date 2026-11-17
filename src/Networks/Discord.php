<?php

/**
 * Pollen: posting to a Discord channel through its webhook.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to a Discord channel through a webhook made in the channel's
 * Integrations settings: the text, and an embed with the item's title, link,
 * excerpt and image. The webhook's address is its secret, so Pollen seals it.
 *
 * @since 1.0.0
 */
final readonly class Discord implements TokenNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'discord';
    }

    public function label(): string
    {
        return __('Discord');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 2000, image: Limits::IMAGE_OPTIONAL, firstComment: false, linkCard: true);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        $url = self::webhook($token);
        $hook = $this->http->get($url);

        if (! isset($hook['id'])) {
            throw ShareFailed::reconnect(__('Discord didn’t recognise that webhook.'));
        }

        return new Connected(['token' => $url], (string) $hook['id'], (string) ($hook['name'] ?? __('Webhook')), options: ['guild' => (string) ($hook['guild_id'] ?? ''), 'discord_channel' => (string) ($hook['channel_id'] ?? '')]);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $message = ['content' => $post->text, 'allowed_mentions' => ['parse' => []]];

        if ($post->link !== '' && $post->linkMode === Post::LINK_CARD) {
            $embed = array_filter(['title' => mb_substr($post->title, 0, 256), 'url' => $post->link, 'description' => mb_substr($post->excerpt, 0, 1000)]);

            if ($post->imageUrl !== '') {
                $embed['image'] = ['url' => $post->imageUrl];
            }

            $message['embeds'] = [$embed];
        } elseif ($post->imageUrl !== '') {
            $message['embeds'] = [['image' => ['url' => $post->imageUrl]]];
        }

        $answer = $this->http->postJson(self::webhook((string) ($credentials['token'] ?? '')).'?wait=true', $message);
        $id = (string) ($answer['id'] ?? '');
        $guild = $channel->option('guild');

        return new Sent($id, $guild !== '' && $id !== '' ? 'https://discord.com/channels/'.$guild.'/'.(string) ($answer['channel_id'] ?? $channel->option('discord_channel')).'/'.$id : '');
    }

    /**
     * Returns the webhook's address after checking it is one of Discord's.
     *
     * @throws ShareFailed when it isn't
     *
     * @since 1.0.0
     */
    public static function webhook(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^https://(?:ptb\.|canary\.)?discord(?:app)?\.com/api/webhooks/(\d+)/([A-Za-z0-9_-]+)$#', $url, $m) !== 1) {
            throw ShareFailed::reconnect(__('That isn’t a Discord webhook address, which starts with https://discord.com/api/webhooks/.'));
        }

        return 'https://discord.com/api/webhooks/'.$m[1].'/'.$m[2];
    }
}
