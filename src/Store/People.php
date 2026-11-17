<?php

/**
 * Pollen: which of Pollen's permissions people hold.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use Illuminate\Support\Facades\DB;

/**
 * Remembers which of Pollen's own permissions each person held, and their
 * time zone, when core last told Pollen in the editor's panel or Pollen's
 * area. A job is told only who it runs for, so this is how it checks who may
 * use which channel, and reads a time the person typed in their own zone.
 *
 * @since 1.0.0
 */
final class People
{
    private const TABLE = 'pollen_people';

    /**
     * Records the permissions core says the person holds now, and their time zone when known.
     *
     * @param  list<string>  $permissions
     *
     * @since 1.0.0
     */
    public function saw(string $personId, array $permissions, string $zone = ''): void
    {
        $row = ['person_id' => $personId, 'permissions' => json_encode(array_values($permissions), JSON_THROW_ON_ERROR), 'seen_at' => now()];
        $update = ['permissions', 'seen_at'];

        if ($zone !== '') {
            $row['zone'] = $zone;
            $update[] = 'zone';
        }

        DB::table(self::TABLE)->upsert([$row], ['person_id'], $update);
    }

    /**
     * Returns the permissions the person held when last seen; none when never seen.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function permissions(string $personId): array
    {
        $json = DB::table(self::TABLE)->where('person_id', $personId)->value('permissions');
        $list = is_string($json) ? json_decode($json, true) : [];

        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    /**
     * Returns the person's time zone as Pollen last saw it, or UTC.
     *
     * @since 1.0.0
     */
    public function zone(string $personId): string
    {
        $zone = DB::table(self::TABLE)->where('person_id', $personId)->value('zone');

        return is_string($zone) && $zone !== '' && in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC';
    }

    /**
     * Forgets a person, for a privacy request or when they leave.
     *
     * @since 1.0.0
     */
    public function forget(string $personId): void
    {
        DB::table(self::TABLE)->where('person_id', $personId)->delete();
    }
}
