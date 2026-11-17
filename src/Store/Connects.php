<?php

/**
 * Pollen: sign-ins under way.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use Illuminate\Support\Facades\DB;

/**
 * Keeps each sign-in that has been started and not finished: its random
 * state, the channel it is for and its PKCE verifier, for half an hour. A
 * state is used once; an expired or unknown one is refused.
 *
 * @since 1.0.0
 */
final class Connects
{
    private const TABLE = 'pollen_connects';

    /** How long a sign-in may take, in seconds. */
    public const LIFETIME = 1800;

    /**
     * Records a started sign-in and returns its state.
     *
     * @since 1.0.0
     */
    public function start(string $channelId, string $verifier): string
    {
        $state = Ids::key();

        DB::table(self::TABLE)->insert([
            'state' => $state,
            'channel_id' => $channelId,
            'verifier' => $verifier,
            'expires_at' => now()->addSeconds(self::LIFETIME),
        ]);

        return $state;
    }

    /**
     * Returns the sign-in for this state and forgets it, or null when it is unknown or expired.
     *
     * @return array{channel_id: string, verifier: string}|null
     *
     * @since 1.0.0
     */
    public function finish(string $state): ?array
    {
        $row = DB::table(self::TABLE)->where('state', $state)->first();
        DB::table(self::TABLE)->where('state', $state)->delete();

        if ($row === null || strtotime((string) $row->expires_at) < time()) {
            return null;
        }

        return ['channel_id' => (string) $row->channel_id, 'verifier' => (string) $row->verifier];
    }

    /**
     * Forgets sign-ins that expired.
     *
     * @since 1.0.0
     */
    public function tidy(): int
    {
        return DB::table(self::TABLE)->where('expires_at', '<', now())->delete();
    }
}
