<?php

/**
 * Pollen: prepared image copies.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use Illuminate\Support\Facades\DB;

/**
 * Keeps the copies of images Pollen resized, cropped or marked for a network,
 * which its own public address serves to the network. Originals stay as they
 * are in the media library; copies are dropped after 30 days.
 *
 * @since 1.0.0
 */
final class Images
{
    private const TABLE = 'pollen_images';

    /**
     * Returns the key of a stored copy of this image for this network, or null.
     *
     * @since 1.0.0
     */
    public function existing(string $source, string $network): ?string
    {
        $key = DB::table(self::TABLE)->where('source', $source)->where('network', $network)->where('created_at', '>=', now()->subDays(25))->value('key');

        return is_string($key) ? $key : null;
    }

    /**
     * Stores a copy and returns the file name its address ends with.
     *
     * @since 1.0.0
     */
    public function store(string $source, string $network, string $mime, string $bytes, int $width, int $height): string
    {
        $key = Ids::key(true);

        DB::table(self::TABLE)->insert([
            'key' => $key,
            'source' => mb_substr($source, 0, 2048),
            'network' => $network,
            'mime' => $mime,
            'bytes' => base64_encode($bytes),
            'width' => $width,
            'height' => $height,
            'created_at' => now(),
        ]);

        return $key;
    }

    /**
     * Returns a copy's type and bytes, or null.
     *
     * @return array{mime: string, bytes: string}|null
     *
     * @since 1.0.0
     */
    public function find(string $key): ?array
    {
        $row = DB::table(self::TABLE)->where('key', $key)->first(['mime', 'bytes']);

        if ($row === null) {
            return null;
        }

        $bytes = base64_decode(is_resource($row->bytes) ? (string) stream_get_contents($row->bytes) : (string) $row->bytes, true);

        return $bytes === false ? null : ['mime' => (string) $row->mime, 'bytes' => $bytes];
    }

    /**
     * Drops copies older than 30 days.
     *
     * @since 1.0.0
     */
    public function tidy(): int
    {
        return DB::table(self::TABLE)->where('created_at', '<', now()->subDays(30))->delete();
    }
}
