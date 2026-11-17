<?php

/**
 * Pollen: the hook that tells other plugins how a share went.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Hooks;

use Teme\Contracts\Hooks\HookContract;
use Teme\Contracts\Hooks\Notification;

/**
 * Tells listeners that a share was sent, or failed for good, after Pollen
 * has recorded it. Listen as `pollen:ShareSent`.
 *
 * @since 1.0.0
 */
#[HookContract]
final readonly class ShareSent implements Notification
{
    /**
     * @param  string  $shareId  the share's ID in Pollen
     * @param  string  $network  the network's key
     * @param  string|null  $itemId  the item's ID, or null for a message of its own
     * @param  bool  $sent  true when the network took it, false when it failed for good
     * @param  string  $url  the post's address on the network, when known
     * @param  string|null  $requestKey  the key of the ShareRequests request it came from, if any
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $shareId,
        public string $network,
        public ?string $itemId,
        public bool $sent,
        public string $url,
        public ?string $requestKey = null,
    ) {}
}
