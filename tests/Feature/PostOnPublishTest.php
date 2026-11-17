<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use KraftySprouts\Pollen\Store\Items;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../Support.php';

uses(TemeTestCase::class);

/** A bot token in Telegram's shape, for tests only. */
const TEST_BOT = '123456:TEST-token-for-pollen-tests-only';

/**
 * Adds a connected Telegram channel that shares new posts.
 *
 * @param  array<string, mixed>  $values
 */
function telegram(array $values = []): string
{
    return channel('telegram', 'Garden club', ['token' => TEST_BOT], ['account' => '-1001', 'options' => ['username' => 'gardenclub'], ...$values]);
}

it('shares a newly published item on the minute’s pass', function (): void {
    $plugin = pollen($this);
    $outbound = $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['message_id' => 31]]);
    telegram();

    $item = $this->publish('Feeding roses in spring');

    expect(shares())->toBe([]);

    expect($plugin->runJob('dispatch')->state)->toBe('finished');

    $share = shares()[0];
    expect($share['status'])->toBe('sent')
        ->and($share['item_id'])->toBe($item->id)
        ->and($share['remote_url'])->toBe('https://t.me/gardenclub/31')
        ->and($share['text'])->toContain('Feeding roses in spring')
        ->and($share['text'])->toContain((string) $item->url);

    $outbound->assertSent(fn ($request): bool => $request->url === 'https://api.telegram.org/bot'.TEST_BOT.'/sendMessage'
        && $request->json()['chat_id'] === '-1001'
        && $request->json()['text'] === $share['text']);
});

it('waits as long as Settings says after publishing', function (): void {
    $plugin = pollen($this, ['delay' => 10]);
    $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['message_id' => 1]]);
    telegram();

    $this->publish('Not yet');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('planned');
    $this->outbound()->assertNothingSent();
});

it('shares an item only once, however often it is published again', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['message_id' => 1]]);
    telegram();

    $item = $this->publish('Once only');
    $plugin->runJob('dispatch');
    $this->publish($item);
    $plugin->runJob('dispatch');

    expect(shares())->toHaveCount(1);
});

it('shares only to channels that share new items of the item’s type', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['message_id' => 1]]);
    telegram(['auto_types' => ['page']]);

    $this->publish('A post');
    $plugin->runJob('dispatch');

    expect(shares())->toBe([]);
});

it('leaves an item alone when its writer turned sharing off', function (): void {
    $plugin = pollen($this);
    $this->outbound();
    telegram();

    $draft = $this->draft('Private thoughts');
    (new Items)->optOut($draft->id, true);
    $this->publish($draft);
    $plugin->runJob('dispatch');

    expect(shares())->toBe([]);
});

it('holds shares while the channel’s sign-in fails, and marks the channel for connecting again', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 401, ['ok' => false, 'description' => 'Unauthorized']);
    $id = telegram();

    $this->publish('First');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('held')
        ->and(shares()[0]['reply'])->toBe('Unauthorized')
        ->and(DB::table('pollen_channels')->where('id', $id)->value('status'))->toBe('reconnect');
});

it('leaves a share to a busy network for the job’s next try', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 502, ['ok' => false, 'description' => 'Bad Gateway']);
    telegram();

    $this->publish('Busy day');
    $plugin->runJob('dispatch');

    // The test's queue tries a job once; on a site, Teme tries it again after its backoff.
    expect(shares()[0]['status'])->toBe('sending')
        ->and(shares()[0]['reply'])->toBe('Try 1 failed: Bad Gateway');
});

it('marks a refused share failed, and counts it against its channel', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 400, ['ok' => false, 'description' => 'Bad Request: chat not found']);
    $id = telegram();

    $this->publish('Nowhere to go');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('failed')
        ->and(shares()[0]['reply'])->toBe('Bad Request: chat not found')
        ->and((int) DB::table('pollen_channels')->where('id', $id)->value('failures'))->toBe(1);
});

it('sends nothing while sharing is paused', function (): void {
    $plugin = pollen($this, ['pause' => true]);
    $this->outbound();
    telegram();

    $this->publish('Paused');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('planned');
    $this->outbound()->assertNothingSent();
});

it('cancels what waits when the item leaves the site', function (): void {
    $plugin = pollen($this, ['delay' => 30]);
    $this->outbound();
    telegram();

    $item = $this->publish('Here and gone');
    $plugin->runJob('dispatch');
    expect(shares()[0]['status'])->toBe('planned');

    $this->unpublish($item);

    expect(shares()[0]['status'])->toBe('cancelled');
});

it('holds a share while the lock key can’t open its channel', function (): void {
    $plugin = pollen($this);
    $this->outbound();
    telegram();
    $plugin->changeSettings(['lock_key' => 'a different lock key, also long enough']);

    $this->publish('Locked out');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('held');
    $this->outbound()->assertNothingSent();
});
