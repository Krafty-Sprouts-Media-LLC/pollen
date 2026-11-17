<?php

/**
 * Pollen: what a network answered to a post.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * A post the network accepted: its ID there, and its address when the
 * network gives one, so the log can link to it.
 *
 * @since 1.0.0
 */
final readonly class Sent
{
    /**
     * @since 1.0.0
     */
    public function __construct(
        public string $remoteId,
        public string $url = '',
        public string $note = '',
    ) {}
}
