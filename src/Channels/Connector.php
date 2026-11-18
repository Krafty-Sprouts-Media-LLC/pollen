<?php

/**
 * Pollen: checking a channel's typed token with its network.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Channels;

use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Networks\ShareFailed;
use KraftySprouts\Pollen\Networks\TokenNetwork;
use KraftySprouts\Pollen\Store\VaultLocked;

/**
 * Opens the token sealed on a channel, asks the network whether it works,
 * and marks the channel connected, or to connect again with the network's
 * words. Shares that waited for the channel go back in the plan. A network
 * that couldn't be reached leaves the channel as it was, so the job tries
 * again later.
 *
 * @since 1.0.0
 */
final readonly class Connector
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Checks the channel; returns whether it is connected now.
     *
     * @throws ShareFailed when the network couldn't be reached, for the job's next try
     *
     * @since 1.0.0
     */
    public function check(string $channelId): bool
    {
        $channel = $this->doors->channels->find($channelId);
        $network = $channel === null ? null : $this->doors->networks->find($channel->network);

        if ($channel === null || ! $network instanceof TokenNetwork) {
            return false;
        }

        try {
            $vault = $this->doors->settings->vault();
            $credentials = $vault->open($channel->sealed);
            $token = (string) ($credentials['token'] ?? $credentials['password'] ?? '');

            if ($token === '') {
                throw ShareFailed::reconnect(__('Give the channel’s token, then save it.'));
            }

            $connected = $network->connect($channel, $token);
        } catch (VaultLocked $locked) {
            $this->doors->channels->needsReconnect($channel, $locked->getMessage());

            return false;
        } catch (ShareFailed $failed) {
            if ($failed->kind === ShareFailed::RETRY) {
                throw $failed;
            }

            $this->doors->channels->needsReconnect($channel, $failed->getMessage());

            return false;
        }

        $this->doors->channels->connected($channel, $connected, $vault);
        $this->doors->shares->release($channel->id);

        return true;
    }
}
