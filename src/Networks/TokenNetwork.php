<?php

/**
 * Pollen: a network connected with what a person types.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * A network whose channel connects with a token, an app password or a
 * webhook address typed into the channel's form, checked with the network
 * when the channel is saved and by Check every channel.
 *
 * @since 1.0.0
 */
interface TokenNetwork extends Network
{
    /**
     * Checks the typed token with the network and returns what to seal.
     *
     * @throws ShareFailed when the network refuses it
     *
     * @since 1.0.0
     */
    public function connect(Channel $channel, string $token): Connected;
}
