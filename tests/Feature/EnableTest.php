<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../bootstrap.php';

uses(TemeTestCase::class);

it('is enabled by an administrator, with every table it lists', function (): void {
    $this->plugin(__DIR__.'/../..')->enable();

    foreach (['pollen_channels', 'pollen_shares', 'pollen_items'] as $table) {
        expect(DB::getSchemaBuilder()->hasTable($table))->toBeTrue();
    }
});
