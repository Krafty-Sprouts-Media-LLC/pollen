<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../Support.php';

uses(TemeTestCase::class);

it('shares a newly published item to a channel that shares new items, on the minute’s pass', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example']]);
    $outbound = $this->outbound()->answer('mastodon.example', 200, ['id' => '111', 'url' => 'https://mastodon.example/@garden/111']);
    channel('mastodon', 'Garden on Mastodon', ['token' => 'test-token'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $item = $this->publish('Feeding roses in spring');

    expect(shares())->toBe([]);

    $run = $plugin->runJob('dispatch');

    expect($run->state)->toBe('finished');
    $share = shares()[0];
    expect($share['status'])->toBe('sent')
        ->and($share['item_id'])->toBe($item->id)
        ->and($share['remote_url'])->toBe('https://mastodon.example/@garden/111')
        ->and($share['text'])->toContain('Feeding roses in spring')
        ->and($share['text'])->toContain((string) $item->url);

    $outbound->assertSent(fn ($request): bool => $request->url === 'https://mastodon.example/api/v1/statuses'
        && $request->json()['status'] === $share['text']
        && sentHeader($request, 'Authorization') === 'Bearer test-token');
});

it('shares an item only once, however often it is published again', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example']]);
    $this->outbound()->answer('mastodon.example', 200, ['id' => '1', 'url' => 'https://mastodon.example/1']);
    channel('mastodon', 'Mastodon', ['token' => 't'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $item = $this->publish('Once only');
    $plugin->runJob('dispatch');
    $this->publish($item);
    $plugin->runJob('dispatch');

    expect(shares())->toHaveCount(1);
});

it('leaves an item alone when its writer turned sharing off', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example']]);
    $this->outbound();
    channel('mastodon', 'Mastodon', ['token' => 't'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $draft = $this->draft('Private thoughts');
    (new KraftySprouts\Pollen\Store\Items)->optOut($draft->id, true);
    $this->publish($draft);
    $plugin->runJob('dispatch');

    expect(shares())->toBe([]);
});

it('holds shares while the channel’s sign-in fails, and tells the managers once', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example']]);
    $this->outbound()->answer('mastodon.example', 401, ['error' => 'The access token is invalid']);
    $id = channel('mastodon', 'Mastodon', ['token' => 'expired'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $this->publish('First');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('held')
        ->and(shares()[0]['reply'])->toBe('The access token is invalid')
        ->and(DB::table('pollen_channels')->where('id', $id)->value('status'))->toBe('reconnect')
        ->and(DB::table('pollen_channels')->where('id', $id)->value('connect_key'))->not->toBeNull();
});

it('leaves a share to a busy network for the job’s next try', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example']]);
    $this->outbound()->answer('mastodon.example', 503, ['error' => 'Down for maintenance']);
    channel('mastodon', 'Mastodon', ['token' => 't'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $this->publish('Busy day');
    $plugin->runJob('dispatch');

    // The test's queue tries a job once; on a site, Teme tries it again after its backoff.
    $share = shares()[0];
    expect($share['status'])->toBe('sending')->and($share['reply'])->toContain('Down for maintenance');
});

it('marks a refused share failed, and counts it against its channel', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example']]);
    $this->outbound()->answer('mastodon.example', 422, ['error' => 'Text character limit of 500 exceeded']);
    $id = channel('mastodon', 'Mastodon', ['token' => 't'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $this->publish('Too long');
    $plugin->runJob('dispatch');

    expect(shares()[0]['status'])->toBe('failed')
        ->and(shares()[0]['reply'])->toBe('Text character limit of 500 exceeded')
        ->and((int) DB::table('pollen_channels')->where('id', $id)->value('failures'))->toBe(1);
});

it('cancels what waits when the item leaves the site', function (): void {
    $plugin = pollen($this, ['servers' => ['https://mastodon.example'], 'delay' => 30]);
    $this->outbound();
    channel('mastodon', 'Mastodon', ['token' => 't'], ['server' => 'https://mastodon.example', 'account' => '42']);

    $item = $this->publish('Here and gone');
    $plugin->runJob('dispatch');
    expect(shares()[0]['status'])->toBe('planned');

    $this->unpublish($item);

    expect(shares()[0]['status'])->toBe('cancelled');
});
