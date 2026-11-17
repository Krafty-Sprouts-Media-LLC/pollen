<?php

/**
 * Pollen: helpers Pollen's feature tests share.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use KraftySprouts\Pollen\Store\Channels;
use KraftySprouts\Pollen\Store\Vault;
use Teme\Testing\PluginUnderTest;

require_once __DIR__.'/bootstrap.php';

/** The lock key Pollen's tests set: a test value, never a real one. */
const POLLEN_TEST_LOCK_KEY = 'pollen test lock key, never used anywhere real';

/**
 * Enables Pollen with a lock key, no wait after publishing, and these settings.
 *
 * @param  array<string, mixed>  $settings
 */
function pollen(object $test, array $settings = []): PluginUnderTest
{
    return $test->plugin(__DIR__.'/..')->enable()->changeSettings([
        'lock_key' => POLLEN_TEST_LOCK_KEY,
        'delay' => 0,
        'utm' => false,
        ...$settings,
    ]);
}

/**
 * Adds a connected channel with sealed test credentials, and returns its ID.
 *
 * @param  array<string, mixed>  $credentials
 * @param  array<string, mixed>  $values
 */
function channel(string $network, string $name, array $credentials, array $values = []): string
{
    $channels = new Channels;
    $id = $channels->save(null, [
        'name' => $name,
        'network' => $network,
        'account' => '',
        'sealed' => (new Vault(POLLEN_TEST_LOCK_KEY))->seal($credentials),
        'status' => 'connected',
        'auto_types' => ['post'],
        ...$values,
    ], '1');

    return $id;
}

/**
 * Returns Pollen's shares, oldest first, as rows.
 *
 * @return list<array<string, mixed>>
 */
function shares(): array
{
    return array_map(fn (object $r): array => (array) $r, DB::table('pollen_shares')->orderBy('created_at')->orderBy('id')->get()->all());
}

/**
 * Returns a header a request carried, by name in any case, or null.
 */
function sentHeader(object $request, string $name): ?string
{
    foreach ($request->headers as $key => $values) {
        if (strtolower((string) $key) === strtolower($name)) {
            return is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }
    }

    return null;
}
