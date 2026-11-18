<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../Support.php';

uses(TemeTestCase::class);

/** A bot token in Telegram's shape, for tests only. */
const CHECK_BOT = '123456:TEST-token-for-pollen-tests-only';

/**
 * Returns a channel row by ID.
 *
 * @return array<string, mixed>
 */
function channelRow(string $id): array
{
    return (array) DB::table('pollen_channels')->where('id', $id)->first();
}

it('connects a Telegram channel once the network confirms its bot and chat', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['id' => -1001, 'title' => 'Garden club', 'username' => 'gardenclub']]);
    $id = channel('telegram', 'Garden', ['token' => CHECK_BOT], ['account' => '-1001', 'status' => 'new']);

    expect($plugin->runJob('check-channel', ['channel' => $id])->state)->toBe('finished');

    $row = channelRow($id);
    expect($row['status'])->toBe('connected')
        ->and($row['account_name'])->toBe('Garden club')
        ->and($row['last_error'])->toBe('')
        ->and(json_decode($row['options'], true)['username'])->toBe('gardenclub');
    $this->outbound()->assertSent(fn ($r): bool => $r->url === 'https://api.telegram.org/bot'.CHECK_BOT.'/getChat');
});

it('marks a channel to connect again when the network refuses its token', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 401, ['ok' => false, 'description' => 'Unauthorized']);
    $id = channel('telegram', 'Garden', ['token' => CHECK_BOT], ['account' => '-1001', 'status' => 'new']);

    $plugin->runJob('check-channel', ['channel' => $id]);

    expect(channelRow($id)['status'])->toBe('reconnect')
        ->and(channelRow($id)['last_error'])->toBe('Unauthorized');
});

it('sends the shares a channel held once it connects', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['id' => -1001, 'title' => 'Garden club', 'message_id' => 9]]);
    $id = channel('telegram', 'Garden', ['token' => CHECK_BOT], ['account' => '-1001', 'status' => 'reconnect']);

    $this->publish('Waiting for sign-in');
    $plugin->runJob('dispatch');
    expect(shares()[0]['status'])->toBe('held');

    $plugin->runJob('check-channel', ['channel' => $id]);
    $plugin->runJob('dispatch');

    expect(channelRow($id)['status'])->toBe('connected')
        ->and(shares()[0]['status'])->toBe('sent');
});

it('leaves a channel as it was when the network can’t be reached, for the job to try again', function (): void {
    $plugin = pollen($this);
    $this->outbound()->answer('api.telegram.org', 502, ['ok' => false, 'description' => 'Bad Gateway']);
    $id = channel('telegram', 'Garden', ['token' => CHECK_BOT], ['account' => '-1001', 'status' => 'new']);

    expect($plugin->runJob('check-channel', ['channel' => $id])->state)->toBe('failed');
    expect(channelRow($id)['status'])->toBe('new');
});
