<?php

/**
 * Pollen: the hook other plugins use to ask Pollen to share.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Hooks;

use Teme\Contracts\Hooks\Collect;
use Teme\Contracts\Hooks\HookContract;

/**
 * Asked each minute, as Pollen sends what is due, for the items other
 * plugins want shared. A listener returns a list of requests, each an array:
 *
 * - `key` (required): up to 100 characters, unique to the request. Pollen
 *   plans a request once, so a listener may return it every minute until
 *   it no longer needs to;
 * - `item` (required): the ID of a published item;
 * - `channels`: channel names or labels, separated by commas; empty for every channel;
 * - `at`: when to share, as an ISO 8601 date and time; empty for now;
 * - `text`: the words to share, which may use Pollen's variables; empty for each channel's template.
 *
 * A request that names no published item, or no channel, is passed over.
 * Listen as `pollen:ShareRequests`, after requiring `krafty-sprouts/pollen`.
 *
 * @since 1.0.0
 */
#[HookContract]
final readonly class ShareRequests implements Collect
{
    /**
     * @param  string  $at  the moment Pollen asks, in ISO 8601
     *
     * @since 1.0.0
     */
    public function __construct(public string $at) {}

    /**
     * @since 1.0.0
     */
    public static function itemType(): string
    {
        return 'array';
    }
}
