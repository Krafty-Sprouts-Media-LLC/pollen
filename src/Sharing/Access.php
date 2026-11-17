<?php

/**
 * Pollen: who may post to which channel.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Doors;

/**
 * Decides whether a person may post to a channel, by the channel's choice:
 * everyone who may share with Pollen, only the people who manage Pollen, or
 * only the people it names and the managers. Pollen's permissions are read
 * from what core last told Pollen the person holds.
 *
 * @since 1.0.0
 */
final readonly class Access
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Returns whether the person may post to the channel.
     *
     * @since 1.0.0
     */
    public function may(string $personId, Channel $channel): bool
    {
        $permissions = $this->doors->people->permissions($personId);
        $manager = in_array('manage', $permissions, true);

        return match ($channel->access) {
            'managers' => $manager,
            'listed' => $manager || in_array($personId, $channel->people, true),
            default => $manager || in_array('share', $permissions, true),
        };
    }
}
