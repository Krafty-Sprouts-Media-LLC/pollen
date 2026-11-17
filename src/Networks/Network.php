<?php

/**
 * Pollen: what every network does.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * One social network Pollen posts to: its key and name, its rules for a
 * post, and how a post is sent with a channel's opened credentials.
 *
 * @since 1.0.0
 */
interface Network
{
    /**
     * Returns the network's key, as channels and templates name it.
     *
     * @since 1.0.0
     */
    public function key(): string;

    /**
     * Returns the network's name as people read it.
     *
     * @since 1.0.0
     */
    public function label(): string;

    /**
     * Returns what the network allows in one post.
     *
     * @since 1.0.0
     */
    public function limits(): Limits;

    /**
     * Sends the post and returns what the network made of it.
     *
     * @param  array<string, mixed>  $credentials  the channel's opened tokens
     *
     * @throws ShareFailed when the network didn't take it
     *
     * @since 1.0.0
     */
    public function publish(Channel $channel, array $credentials, Post $post): Sent;
}
