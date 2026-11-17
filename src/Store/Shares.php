<?php

/**
 * Pollen: the shares table, Pollen's plan and log at once.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Every share Pollen plans, sends or suggests, one row a channel: what goes,
 * where and when, how it went and what the network said. Planned shares are
 * the planner; sent and failed ones are the log.
 *
 * @since 1.0.0
 */
final class Shares
{
    private const TABLE = 'pollen_shares';

    /** Waiting for its time. */
    public const PLANNED = 'planned';

    /** Suggested, waiting for a person to choose it. */
    public const SUGGESTED = 'suggested';

    /** Being sent now. */
    public const SENDING = 'sending';

    /** Sent. */
    public const SENT = 'sent';

    /** Failed for good. */
    public const FAILED = 'failed';

    /** Held: its channel must be connected again, or a person paused it. */
    public const HELD = 'held';

    /** Cancelled: its item left the site. */
    public const CANCELLED = 'cancelled';

    /**
     * Plans one share and returns its ID, or null when $requestKey was planned before.
     *
     * @param  array{channel_id: string, origin: string, item_id?: string|null, item_title?: string, item_url?: string, automation_id?: string|null, message?: string|null, text?: string|null, image_url?: string, first_comment?: string, requested_by?: string, request_key?: string|null, status?: string}  $share
     *
     * @since 1.0.0
     */
    public function plan(array $share, DateTimeInterface $at): ?string
    {
        if (($share['request_key'] ?? null) !== null && DB::table(self::TABLE)->where('request_key', $share['request_key'])->exists()) {
            return null;
        }

        $id = Ids::make();
        $now = now();

        DB::table(self::TABLE)->insert([
            'status' => self::PLANNED,
            'item_title' => '',
            'item_url' => '',
            'image_url' => '',
            'first_comment' => '',
            'requested_by' => '',
            ...$share,
            'id' => $id,
            'send_at' => $at,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * Returns one share's row, or null.
     *
     * @return array<string, mixed>|null
     *
     * @since 1.0.0
     */
    public function find(string $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * Returns the IDs of planned shares whose time has come, oldest first.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function due(int $limit = 50): array
    {
        return array_values(array_map('strval', DB::table(self::TABLE)->where('status', self::PLANNED)->where('send_at', '<=', now())->orderBy('send_at')->limit($limit)->pluck('id')->all()));
    }

    /**
     * Marks a planned share as being sent, and returns false when another run already took it.
     *
     * @since 1.0.0
     */
    public function claim(string $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->where('status', self::PLANNED)->update(['status' => self::SENDING, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]) === 1;
    }

    /**
     * Records the text a share was sent with, once worked out.
     *
     * @since 1.0.0
     */
    public function written(string $id, string $text, string $imageUrl, string $itemTitle = '', string $itemUrl = ''): void
    {
        DB::table(self::TABLE)->where('id', $id)->update(array_filter([
            'text' => $text,
            'image_url' => $imageUrl,
            'item_title' => $itemTitle,
            'item_url' => $itemUrl,
            'updated_at' => now(),
        ], fn (mixed $v): bool => $v !== ''));
    }

    /**
     * Records a share as sent, with the network's ID and address for it.
     *
     * @since 1.0.0
     */
    public function sent(string $id, string $remoteId, string $remoteUrl, string $note = ''): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => self::SENT,
            'sent_at' => now(),
            'remote_id' => mb_substr($remoteId, 0, 255),
            'remote_url' => mb_substr($remoteUrl, 0, 2048),
            'reply' => mb_substr($note !== '' ? $note : __('Posted.'), 0, 500),
            'updated_at' => now(),
        ]);
    }

    /**
     * Puts a share back in the plan to try again at $at, with the network's words.
     *
     * @since 1.0.0
     */
    public function again(string $id, DateTimeInterface $at, string $why): void
    {
        DB::table(self::TABLE)->where('id', $id)->update(['status' => self::PLANNED, 'send_at' => $at, 'reply' => mb_substr($why, 0, 500), 'updated_at' => now()]);
    }

    /**
     * Records a share as failed for good, or held, with the network's words.
     *
     * @since 1.0.0
     */
    public function stopped(string $id, string $status, string $why): void
    {
        DB::table(self::TABLE)->where('id', $id)->update(['status' => $status, 'reply' => mb_substr($why, 0, 500), 'updated_at' => now()]);
    }

    /**
     * Puts the held shares of a channel that connected again back in the plan, now.
     *
     * @since 1.0.0
     */
    public function release(string $channelId): int
    {
        return DB::table(self::TABLE)->where('channel_id', $channelId)->where('status', self::HELD)->update(['status' => self::PLANNED, 'send_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Plans, at $at, the shares a writer made for an item before it was published, which waited for it.
     *
     * @since 1.0.0
     */
    public function releaseItem(string $itemId, DateTimeInterface $at): int
    {
        return DB::table(self::TABLE)->where('item_id', $itemId)->where('status', self::HELD)->where('origin', 'panel')->whereNull('sent_at')->where('reply', 'awaiting-publication')->update(['status' => self::PLANNED, 'send_at' => $at, 'reply' => '', 'updated_at' => now()]);
    }

    /**
     * Cancels the shares of an item that haven't gone yet, as the item left the site.
     *
     * @since 1.0.0
     */
    public function cancelItem(string $itemId, string $why): int
    {
        return DB::table(self::TABLE)->where('item_id', $itemId)->whereIn('status', [self::PLANNED, self::SUGGESTED, self::HELD])->update(['status' => self::CANCELLED, 'reply' => $why, 'updated_at' => now()]);
    }

    /**
     * Returns an item's shares, newest first.
     *
     * @return list<array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function forItem(string $itemId, int $limit = 50): array
    {
        return array_values(array_map(fn (object $r): array => (array) $r, DB::table(self::TABLE)->where('item_id', $itemId)->orderByDesc('created_at')->limit($limit)->get()->all()));
    }

    /**
     * Returns when an item was last sent to a channel, or null.
     *
     * @since 1.0.0
     */
    public function lastSent(string $itemId, string $channelId): ?int
    {
        $at = DB::table(self::TABLE)->where('item_id', $itemId)->where('channel_id', $channelId)->where('status', self::SENT)->max('sent_at');

        return $at === null ? null : (int) strtotime((string) $at);
    }

    /**
     * Returns the IDs of items an automation shared since $since, or ever when null.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function sharedBy(string $automationId, ?DateTimeInterface $since): array
    {
        $query = DB::table(self::TABLE)->where('automation_id', $automationId)->whereIn('status', [self::PLANNED, self::SENDING, self::SENT])->whereNotNull('item_id');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return array_values(array_unique(array_map('strval', $query->pluck('item_id')->all())));
    }

    /**
     * Returns how often each item was shared, for the least-shared order.
     *
     * @param  list<string>  $itemIds
     * @return array<string, int>
     *
     * @since 1.0.0
     */
    public function timesShared(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_map('intval', DB::table(self::TABLE)->whereIn('item_id', $itemIds)->where('status', self::SENT)->groupBy('item_id')->selectRaw('item_id, count(*) as n')->pluck('n', 'item_id')->all());
    }

    /**
     * Returns the number of shares by status since $since, or of all planned ones.
     *
     * @since 1.0.0
     */
    public function count(string $status, ?DateTimeInterface $since = null): int
    {
        $query = DB::table(self::TABLE)->where('status', $status);

        if ($since !== null) {
            $query->where('updated_at', '>=', $since);
        }

        return $query->count();
    }
}
