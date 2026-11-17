<?php

/**
 * Pollen: planning shares.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use DateTimeImmutable;
use KraftySprouts\Pollen\Automations\Automation;
use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Hooks\ShareRequests;
use Teme\Contracts\Content\ReadRefused;
use Throwable;

/**
 * Turns what happened into planned shares, one a channel: a newly published
 * item for the channels that share new items and the automations that match
 * it, a writer's own plan from the editor or the planner, and the requests
 * other plugins return to ShareRequests. A plan is made once per key, so
 * hearing the same thing twice never shares it twice.
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
            $facts = ItemFacts::read($this->doors->content, null, $itemId, $personId);
        } catch (ReadRefused) {
            return 0;
        }

        if ($facts->status !== 'published') {
            return 0;
        }

        $at = now()->addMinutes(max(0, $this->doors->settings->int('delay')));
        $this->doors->shares->releaseItem($itemId, $at);
        $targets = [];

        foreach ($this->doors->channels->forNewItems($facts->type) as $channel) {
            $targets[$channel->id] = ['at' => $at, 'automation' => null, 'key' => 'publish:'.$itemId.':'.$channel->id];
        }

        // An automation that matches says which template a channel gets, and replaces the plain share.
        foreach ($this->doors->automations->active(Automation::PUBLISH) as $automation) {
            if (! $automation->matches($itemId, $facts->type, $facts->url, $facts->terms)) {
                continue;
            }

            foreach ($this->doors->channels->named($automation->text('channels')) as $channel) {
                if ($channel->ready()) {
                    $targets[$channel->id] = [
                        'at' => now()->addMinutes(max(0, $automation->number('delay'))),
                        'automation' => $automation,
                        'key' => 'rule:'.$automation->id.':'.$itemId.':'.$channel->id,
                    ];
                }
            }
        }

        $captions = new Captions($this->doors);
        $planned = 0;

        foreach ($targets as $channelId => $target) {
            $message = null;

            if ($target['automation'] instanceof Automation && (bool) ($target['automation']->settings['ai'] ?? false)) {
                $message = $captions->one($facts, (string) $this->doors->channels->find($channelId)?->network, '', $personId);
            }

            $planned += $this->doors->shares->plan([
                'channel_id' => $channelId,
                'origin' => $target['automation'] === null ? 'publish' : 'rule',
                'automation_id' => $target['automation']?->id,
                'item_id' => $itemId,
                'item_title' => $facts->title,
                'item_url' => $facts->url,
                'message' => $message,
                'request_key' => $target['key'],
            ], $target['at']) !== null ? 1 : 0;
        }

        return $planned;
    }

    /**
     * Plans a person's own share of an item, or of a message, to the channels they may use among those named.
     *
     * @param  list<string>  $unknown  the words that named no channel
     * @param  list<string>  $refused  the channels the person may not use
     * @return list<string> the IDs of the shares planned
     *
     * @since 1.0.0
     */
    public function byPerson(string $personId, ?ItemFacts $item, string $channels, string $message, DateTimeImmutable $at, string $image = '', string $firstComment = '', string $origin = 'panel', array &$unknown = [], array &$refused = [], string $status = 'planned'): array
    {
        $access = new Access($this->doors);
        $planned = [];
        $refused = [];

        foreach ($this->doors->channels->named($channels, $unknown) as $channel) {
            if (! $channel->active) {
                continue;
            }

            if (! $access->may($personId, $channel)) {
                $refused[] = $channel->name;

                continue;
            }

            $id = $this->doors->shares->plan([
                'channel_id' => $channel->id,
                'origin' => $origin,
                'item_id' => $item?->id,
                'item_title' => $item->title ?? '',
                'item_url' => $item->url ?? '',
                'message' => trim($message) !== '' ? $message : null,
                'image_url' => $image,
                'first_comment' => $firstComment,
                'requested_by' => $personId,
                'status' => $status,
            ], $at);

            if ($id !== null) {
                $planned[] = $id;
            }
        }

        return $planned;
    }

    /**
     * Asks other plugins for the items they want shared, and plans each request once; returns how many shares were planned.
     *
     * @since 1.0.0
     */
    public function requests(): int
    {
        if ($this->doors->hooks === null) {
            return 0;
        }

        try {
            $requests = $this->doors->hooks->collect(new ShareRequests(now()->toIso8601String()));
        } catch (Throwable) {
            return 0;
        }

        $planned = 0;

        foreach (array_slice($requests, 0, 100) as $request) {
            if (! is_array($request) || ! is_string($request['key'] ?? null) || ! is_string($request['item'] ?? null)) {
                continue;
            }

            $key = 'hook:'.mb_substr($request['key'], 0, 100);
            $itemId = strtolower(trim($request['item']));

            if ($this->doors->items->optedOut($itemId)) {
                continue;
            }

            try {
                $at = is_string($request['at'] ?? null) && $request['at'] !== '' ? new DateTimeImmutable($request['at']) : now()->toDateTimeImmutable();
            } catch (Throwable) {
                continue;
            }

            foreach ($this->doors->channels->named(is_string($request['channels'] ?? null) ? $request['channels'] : '') as $channel) {
                if (! $channel->ready()) {
                    continue;
                }

                $planned += $this->doors->shares->plan([
                    'channel_id' => $channel->id,
                    'origin' => 'hook',
                    'item_id' => $itemId,
                    'message' => is_string($request['text'] ?? null) && trim($request['text']) !== '' ? mb_substr($request['text'], 0, 5000) : null,
                    'request_key' => mb_substr($key.':'.$channel->id, 0, 120),
                ], $at) !== null ? 1 : 0;
            }
        }

        return $planned;
    }
}
