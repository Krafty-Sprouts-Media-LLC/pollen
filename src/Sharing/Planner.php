<?php

/**
 * Pollen: planning the shares of newly published items.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Doors;
use Teme\Contracts\Content\ReadRefused;

/**
 * Turns a newly published item into planned shares, one for each channel
 * that shares new items of its type, after the wait set in Settings. A plan
 * is made once per item and channel, so hearing a publication twice never
 * shares it twice.
 *
 * @since 1.0.0
 */
final readonly class Planner
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Plans the shares of a newly published item, reading it as $personId; returns how many were planned.
     *
     * @param  array<string, mixed>  $item  a row of pollen_items
     *
     * @since 1.0.0
     */
    public function published(array $item, string $personId): int
    {
        $itemId = (string) $item['item_id'];

        if ($this->doors->items->optedOut($itemId) || $this->doors->content === null) {
            return 0;
        }

        try {
            $facts = ItemFacts::read($this->doors->content, $this->doors->peopleReader, $itemId, $personId, $this->doors->items->actor($itemId));
        } catch (ReadRefused) {
            return 0;
        }

        if ($facts->status !== 'published') {
            return 0;
        }

        $at = now()->addMinutes(max(0, $this->doors->settings->int('delay')));
        $planned = 0;

        foreach ($this->doors->channels->forNewItems($facts->type) as $channel) {
            $planned += $this->doors->shares->plan([
                'channel_id' => $channel->id,
                'origin' => 'publish',
                'item_id' => $itemId,
                'item_title' => $facts->title,
                'item_url' => $facts->url,
                'request_key' => 'publish:'.$itemId.':'.$channel->id,
            ], $at) !== null ? 1 : 0;
        }

        return $planned;
    }
}
