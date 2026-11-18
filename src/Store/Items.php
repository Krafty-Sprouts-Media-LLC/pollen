<?php

/**
 * Pollen: what Pollen remembers about the site's items.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Remembers what Pollen heard about each item: when it was first published,
 * its address and type, whether its writer turned sharing off, and whether a
 * new publication still waits for Pollen to plan its shares.
 *
 * @since 1.0.0
 */
final class Items
{
    private const TABLE = 'pollen_items';

    /**
     * Records a publication Pollen heard, and returns whether it waits to be planned.
     *
     * @since 1.0.0
     */
    public function published(string $itemId, string $type, string $title, string $url, DateTimeInterface $firstPublished, bool $first, ?string $actorId = null): bool
    {
        $exists = DB::table(self::TABLE)->where('item_id', $itemId)->exists();
        $values = ['type' => $type, 'title' => mb_substr($title, 0, 512), 'url' => mb_substr($url, 0, 2048), 'gone' => false, 'updated_at' => now()];

        if ($actorId !== null && $actorId !== '') {
            $values['actor_id'] = $actorId;
        }

        if ($exists) {
            DB::table(self::TABLE)->where('item_id', $itemId)->update($values + ($first ? ['panel_note' => 'plan'] : []));
        } else {
            DB::table(self::TABLE)->insert($values + [
                'item_id' => $itemId,
                'published_at' => $firstPublished,
                'panel_note' => $first ? 'plan' : '',
                'created_at' => now(),
            ]);
        }

        return $first;
    }

    /**
     * Returns the items that wait for Pollen to plan their shares, and marks them taken.
     *
     * @return list<array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function takeWaiting(int $limit = 20): array
    {
        $rows = array_map(fn (object $r): array => (array) $r, DB::table(self::TABLE)->where('panel_note', 'plan')->orderBy('updated_at')->limit($limit)->get()->all());
        $ids = array_column($rows, 'item_id');

        if ($ids !== []) {
            DB::table(self::TABLE)->whereIn('item_id', $ids)->update(['panel_note' => '']);
        }

        return array_values($rows);
    }

    /**
     * Returns who last published the item, which stands in for its owner when it credits nobody; empty when unknown.
     *
     * @since 1.0.0
     */
    public function actor(string $itemId): string
    {
        return (string) DB::table(self::TABLE)->where('item_id', $itemId)->value('actor_id');
    }

    /**
     * Records that an item left the site.
     *
     * @since 1.0.0
     */
    public function gone(string $itemId): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->update(['gone' => true, 'panel_note' => '', 'updated_at' => now()]);
    }

    /**
     * Turns sharing on or off for an item.
     *
     * @since 1.0.0
     */
    public function optOut(string $itemId, bool $out): void
    {
        $exists = DB::table(self::TABLE)->where('item_id', $itemId)->exists();

        if ($exists) {
            DB::table(self::TABLE)->where('item_id', $itemId)->update(['opted_out' => $out, 'updated_at' => now()]);
        } else {
            DB::table(self::TABLE)->insert(['item_id' => $itemId, 'opted_out' => $out, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Returns whether the item's writer turned sharing off.
     *
     * @since 1.0.0
     */
    public function optedOut(string $itemId): bool
    {
        return (bool) DB::table(self::TABLE)->where('item_id', $itemId)->value('opted_out');
    }

    /**
     * Returns the IDs of every item whose writer turned sharing off.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function optedOutIds(): array
    {
        return array_values(array_map('strval', DB::table(self::TABLE)->where('opted_out', true)->pluck('item_id')->all()));
    }

    /**
     * Returns when items were first published, as Pollen heard it, by ID.
     *
     * @param  list<string>  $itemIds
     * @return array<string, int>
     *
     * @since 1.0.0
     */
    public function publishedAt(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $times = [];

        foreach (DB::table(self::TABLE)->whereIn('item_id', $itemIds)->whereNotNull('published_at')->get(['item_id', 'published_at']) as $row) {
            $times[(string) $row->item_id] = (int) strtotime((string) $row->published_at);
        }

        return $times;
    }

    /**
     * Records that an item was shared once more.
     *
     * @since 1.0.0
     */
    public function shared(string $itemId): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->update(['times_shared' => DB::raw('times_shared + 1'), 'last_shared_at' => now()]);
    }
}
