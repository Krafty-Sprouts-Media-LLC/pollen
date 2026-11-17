<?php

declare(strict_types=1);

namespace KraftySprouts\Pollen;

use Illuminate\Support\Facades\DB;
use Teme\Contracts\Content\ContentPublished;
use Teme\Contracts\Hooks\Hooks;
use Teme\Contracts\Plugins\Plugin;

/**
 * Spike.
 */
final class Pollen implements Plugin
{
    public function register(Hooks $hooks): void
    {
        $hooks->on(ContentPublished::class, function (ContentPublished $published): void {
            DB::table('pollen_channels')->insert(['id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'name' => $published->item->title]);
        });
    }
}
