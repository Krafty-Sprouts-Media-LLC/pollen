<?php

/**
 * Pollen: the hook that lets other plugins change a share's words.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Hooks;

use Teme\Contracts\Hooks\HookContract;
use Teme\Contracts\Hooks\Transform;

/**
 * Hands a share's finished text to listeners just before it is sent, one
 * after another, with the network, the channel and the item it is for. A
 * listener returns the text to send; Pollen fits the result to the network
 * again. Listen as `pollen:ShareText`.
 *
 * @since 1.0.0
 */
#[HookContract]
final readonly class ShareText implements Transform
{
    /**
     * @param  string  $text  the text Pollen wrote
     * @param  string  $network  the network's key, such as mastodon
     * @param  string  $channelId  the channel's ID in Pollen
     * @param  string|null  $itemId  the item's ID, or null for a message of its own
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $text,
        public string $network,
        public string $channelId,
        public ?string $itemId,
    ) {}

    /**
     * @since 1.0.0
     */
    public static function valueType(): string
    {
        return 'string';
    }

    /**
     * @since 1.0.0
     */
    public function value(): mixed
    {
        return $this->text;
    }
}
