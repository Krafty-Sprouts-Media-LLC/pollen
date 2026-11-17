<?php

/**
 * Pollen: re-sharing older items.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use DateTimeImmutable;
use DateTimeZone;
use KraftySprouts\Pollen\Automations\Automation;
use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Doors;
use Teme\Contracts\Content\ItemQuery;
use Teme\Contracts\Content\ItemRef;
use Throwable;

/**
 * Runs one turn of a re-share automation: gathers the published items that
 * match it, leaves out the too new, the excluded, the opted out and those
 * shared too recently, picks some in the order it asks, and plans their
 * shares, to every channel or to each in turn. When every item has been
 * shared and the automation waits for that, a new round starts.
 *
 * @since 1.0.0
 */
final readonly class Resharer
{
    /** The most items one turn reads, two hundred a page. */
    private const MOST_ITEMS = 2000;

    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Runs the automation's turn as $personId and returns a note for its row.
     *
     * @since 1.0.0
     */
    public function run(Automation $automation, string $personId, DateTimeZone $zone): string
    {
        $now = now()->toDateTimeImmutable();
        $channels = array_values(array_filter($this->doors->channels->named($automation->text('channels')), fn ($c): bool => $c->ready()));
        $next = $automation->nextRun($now, $zone);

        if ($channels === []) {
            $this->doors->automations->ran($automation->id, $next, $automation->turn, __('No channel it names is connected.'));

            return __('No channel it names is connected.');
        }

        if ($this->doors->settings->bool('pause')) {
            $this->doors->automations->ran($automation->id, $now->modify('+15 minutes'), $automation->turn, __('Sharing is paused.'));

            return __('Sharing is paused.');
        }

        $pool = $this->pool($automation, $now);
        $repeatAfter = $automation->number('repeat_after');
        $shared = $this->doors->shares->sharedBy($automation->id, $repeatAfter > 0 ? $now->modify('-'.$repeatAfter.' days') : $this->roundStart($automation));
        $fresh = array_values(array_filter($pool, fn (ItemRef $i): bool => ! in_array($i->id, $shared, true)));
        $note = '';

        if ($fresh === [] && $pool !== [] && $repeatAfter === 0) {
            // Every item has had its turn: start a new round.
            $automation = $this->newRound($automation, $now);
            $fresh = $pool;
            $note = __('Every matching item was shared; a new round began.').' ';
        }

        if ($fresh === []) {
            $this->doors->automations->ran($automation->id, $next, $automation->turn, __('No item matches it now.'));

            return __('No item matches it now.');
        }

        $picks = $this->pick($fresh, $automation->text('order'), max(1, min(5, $automation->number('per_run', 1))));
        $turn = $automation->turn;
        $planned = 0;
        $captions = new Captions($this->doors);

        foreach ($picks as $item) {
            $targets = (bool) ($automation->settings['rotate'] ?? false) ? [$channels[$turn++ % count($channels)]] : $channels;

            foreach ($targets as $channel) {
                $message = null;

                if ((bool) ($automation->settings['ai'] ?? false) && $this->doors->content !== null) {
                    try {
                        $message = $captions->one(ItemFacts::read($this->doors->content, null, $item->id, $personId), $channel->network, '', $personId);
                    } catch (Throwable) {
                        $message = null;
                    }
                }

                $planned += $this->doors->shares->plan([
                    'channel_id' => $channel->id,
                    'origin' => 'reshare',
                    'automation_id' => $automation->id,
                    'item_id' => $item->id,
                    'item_title' => $item->title,
                    'item_url' => (string) $item->url,
                    'message' => $message,
                ], $now) !== null ? 1 : 0;
            }
        }

        $note .= trans_choice('{1} Planned :count share.|[2,*] Planned :count shares.', $planned, ['count' => $planned]);
        $this->doors->automations->ran($automation->id, $next, $turn, $note);

        return $note;
    }

    /**
     * Returns the published items the automation covers, without the too new, the excluded and the opted out, newest first.
     *
     * @return list<ItemRef>
     *
     * @since 1.0.0
     */
    public function pool(Automation $automation, DateTimeImmutable $now): array
    {
        $terms = array_filter(['category' => $automation->words('categories'), 'tag' => $automation->words('tags')]);
        $newerThan = $automation->number('newer_than');
        $olderThan = $automation->number('older_than');
        $since = $newerThan > 0 ? $now->modify('-'.$newerThan.' days') : null;

        $items = $this->walk($automation->types(), $terms, $since);

        $leaveOut = [...$automation->excludedItems(), ...$this->doors->items->optedOutIds()];

        if ($olderThan > 0) {
            // Items published inside the last $olderThan days are too new.
            foreach ($this->walk($automation->types(), $terms, $now->modify('-'.$olderThan.' days')) as $tooNew) {
                $leaveOut[] = $tooNew->id;
            }
        }

        foreach ($automation->words('exclude_terms') as $slug) {
            foreach (['category', 'tag'] as $taxonomy) {
                try {
                    foreach ($this->walk($automation->types(), [$taxonomy => [$slug]], null) as $excluded) {
                        $leaveOut[] = $excluded->id;
                    }
                } catch (Throwable) {
                    // A site without this taxonomy has nothing to leave out under it.
                }
            }
        }

        return array_values(array_filter($items, function (ItemRef $item) use ($leaveOut): bool {
            foreach ($leaveOut as $out) {
                if ($out === $item->id || ($item->url !== null && rtrim($out, '/') === rtrim(mb_strtolower($item->url), '/'))) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Returns the published items of these types and terms since a moment, newest first, at most MOST_ITEMS.
     *
     * @param  list<string>  $types
     * @param  array<string, list<string>>  $terms
     * @return list<ItemRef>
     */
    private function walk(array $types, array $terms, ?DateTimeImmutable $since): array
    {
        if ($this->doors->content === null) {
            return [];
        }

        $items = [];
        $after = null;

        do {
            $page = $this->doors->content->published(new ItemQuery(types: array_slice($types, 0, 10), terms: $terms, publishedSince: $since, after: $after, limit: 200));
            array_push($items, ...$page->items);
            $after = $page->next;
        } while ($after !== null && count($items) < self::MOST_ITEMS);

        return $items;
    }

    /**
     * Returns $count items picked from the pool: at random, the oldest first, or the least shared first.
     *
     * @param  list<ItemRef>  $pool
     * @return list<ItemRef>
     *
     * @since 1.0.0
     */
    public function pick(array $pool, string $order, int $count): array
    {
        if ($order === 'oldest') {
            return array_slice(array_reverse($pool), 0, $count);
        }

        if ($order === 'least') {
            $times = $this->doors->shares->timesShared(array_map(fn (ItemRef $i): string => $i->id, $pool));
            $indexed = array_keys($pool);
            usort($indexed, fn (int $a, int $b): int => [($times[$pool[$a]->id] ?? 0), -$a] <=> [($times[$pool[$b]->id] ?? 0), -$b]);

            return array_map(fn (int $i): ItemRef => $pool[$i], array_slice($indexed, 0, $count));
        }

        $keys = array_rand($pool, min($count, count($pool)));

        return array_map(fn (int $k): ItemRef => $pool[$k], is_array($keys) ? $keys : [$keys]);
    }

    /**
     * Returns when the automation's current round began, so items shared since then wait.
     */
    private function roundStart(Automation $automation): ?DateTimeImmutable
    {
        $start = $automation->settings['_round'] ?? null;

        return is_string($start) && $start !== '' ? new DateTimeImmutable($start) : null;
    }

    /**
     * Starts a new round from now and returns the automation as it now is.
     */
    private function newRound(Automation $automation, DateTimeImmutable $now): Automation
    {
        $settings = [...$automation->settings, '_round' => $now->format(DATE_ATOM)];
        $this->doors->automations->save($automation->id, $automation->name, $automation->kind, $settings, $automation->createdBy);

        return new Automation($automation->id, $automation->name, $automation->kind, $automation->active, $settings, $automation->nextRunAt, $automation->turn, $automation->createdBy);
    }
}
