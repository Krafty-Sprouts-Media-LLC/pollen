<?php

declare(strict_types=1);

use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../Support.php';

uses(TemeTestCase::class);

/**
 * Publishes one item, runs the minute's pass, and returns the share and what was sent.
 *
 * @return array{0: array<string, mixed>, 1: list<\Teme\Testing\SentRequest>}
 */
function shareOnce(object $test, string $title = 'Feeding roses'): array
{
    $plugin = $test->plugin(__DIR__.'/../..');
    $test->publish($title);
    $plugin->runJob('dispatch');

    return [shares()[0], $test->outbound()->sent()];
}

it('posts a Facebook Page link post with its first comment', function (): void {
    pollen($this);
    $this->outbound()->answerInTurn('graph.facebook.com', [200, 200])->answer('graph.facebook.com', 200, ['id' => '555_777']);
    channel('facebook', 'Page', ['token' => 'page-token'], ['account' => '555', 'options' => ['first_comment' => 'More: {link}']]);

    [$share, $sent] = shareOnce($this);

    expect($share['status'])->toBe('sent')->and($share['remote_url'])->toBe('https://www.facebook.com/555_777');
    expect($sent[0]->url)->toBe('https://graph.facebook.com/v23.0/555/feed')
        ->and($sent[0]->form())->toMatchArray(['access_token' => 'page-token'])
        ->and($sent[0]->form()['link'])->toContain('feeding-roses')
        ->and($sent[1]->url)->toBe('https://graph.facebook.com/v23.0/555_777/comments')
        ->and($sent[1]->form()['message'])->toContain('More: http');
});

it('posts to Instagram as a container then its publication, and refuses without an image', function (): void {
    pollen($this);
    $this->outbound()->answer('graph.facebook.com', 200, ['id' => 'c1']);
    channel('instagram', 'Insta', ['token' => 't'], ['account' => '1784']);

    [$share] = shareOnce($this);

    expect($share['status'])->toBe('failed')->and($share['reply'])->toBe('Instagram needs an image, and this share has none.');
});

it('posts to X, with links counted as X counts them', function (): void {
    pollen($this);
    $this->outbound()->answer('api.x.com', 201, ['data' => ['id' => '1800']]);
    channel('x', 'X', ['access_token' => 'x-token', 'expires_at' => time() + 7000], ['account' => '9', 'options' => ['username' => 'garden']]);

    [$share, $sent] = shareOnce($this, str_repeat('Long title ', 40));

    expect($share['status'])->toBe('sent')->and($share['remote_url'])->toBe('https://x.com/garden/status/1800');
    expect($sent[0]->url)->toBe('https://api.x.com/2/tweets')
        ->and(KraftySprouts\Pollen\Compose\Fitter::length($sent[0]->json()['text'], 23))->toBeLessThanOrEqual(280)
        ->and($sent[0]->json()['text'])->toContain('http');
});

it('renews X access that is about to expire before posting', function (): void {
    pollen($this);
    $this->outbound()->answerInTurn('api.x.com', [200, 201])->answer('api.x.com', 200, ['access_token' => 'new', 'refresh_token' => 'r2', 'expires_in' => 7200, 'data' => ['id' => '5']]);
    channel('x', 'X', ['access_token' => 'old', 'refresh_token' => 'r1', 'expires_at' => time() + 60], ['account' => '9']);

    [$share, $sent] = shareOnce($this);

    expect($sent[0]->url)->toBe('https://api.x.com/2/oauth2/token')
        ->and($sent[0]->form())->toMatchArray(['grant_type' => 'refresh_token', 'refresh_token' => 'r1'])
        ->and(sentHeader($sent[1], 'Authorization'))->toBe('Bearer new');
});

it('posts to LinkedIn with the link as an article and the post’s ID from its header', function (): void {
    pollen($this);
    $this->outbound()->answer('api.linkedin.com', 201, '', ['x-restli-id' => 'urn:li:share:7000']);
    channel('linkedin', 'LinkedIn', ['access_token' => 'li', 'expires_at' => time() + 86400 * 30], ['account' => 'urn:li:person:abc']);

    [$share, $sent] = shareOnce($this);

    expect($share['status'])->toBe('sent')->and($share['remote_url'])->toBe('https://www.linkedin.com/feed/update/urn:li:share:7000/');
    expect($sent[0]->json())->toMatchArray(['author' => 'urn:li:person:abc', 'visibility' => 'PUBLIC'])
        ->and($sent[0]->json()['content']['article']['source'])->toContain('feeding-roses');
});

it('pins to Pinterest only with an image', function (): void {
    pollen($this);
    $this->outbound();
    channel('pinterest', 'Board', ['access_token' => 'p', 'expires_at' => time() + 86400 * 20], ['account' => 'b1']);

    [$share] = shareOnce($this);

    expect($share['status'])->toBe('failed')->and($share['reply'])->toBe('Pinterest needs an image, and this share has none.');
});

it('sends a Telegram message to the chat', function (): void {
    pollen($this);
    $this->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['message_id' => 31]]);
    channel('telegram', 'Telegram', ['token' => '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ'], ['account' => '-1001', 'options' => ['username' => 'gardenclub', 'extra' => 'silent']]);

    [$share, $sent] = shareOnce($this);

    expect($share['status'])->toBe('sent')->and($share['remote_url'])->toBe('https://t.me/gardenclub/31');
    expect($sent[0]->url)->toBe('https://api.telegram.org/bot123456:ABCDEFGHIJKLMNOPQRSTUVWXYZ/sendMessage')
        ->and($sent[0]->json())->toMatchArray(['chat_id' => '-1001', 'disable_notification' => true]);
});

it('posts to Bluesky with a link card and marked hashtags', function (): void {
    pollen($this, ['template' => '{title} #Roses']);
    $this->outbound()->answerInTurn('bsky.social', [200, 200])
        ->answer('bsky.social', 200, ['accessJwt' => 'jwt', 'did' => 'did:plc:abc', 'handle' => 'garden.bsky.social', 'uri' => 'at://did:plc:abc/app.bsky.feed.post/3k', 'cid' => 'c']);
    channel('bluesky', 'Bluesky', ['password' => 'app-pass'], ['handle' => 'garden.bsky.social', 'account' => 'did:plc:abc', 'account_name' => '@garden.bsky.social']);

    [$share, $sent] = shareOnce($this);

    expect($share['status'])->toBe('sent')->and($share['remote_url'])->toBe('https://bsky.app/profile/garden.bsky.social/post/3k');
    $record = $sent[1]->json()['record'];
    expect($record['text'])->toBe('Feeding roses #Roses')
        ->and($record['facets'][0]['features'][0])->toBe(['$type' => 'app.bsky.richtext.facet#tag', 'tag' => 'Roses'])
        ->and($record['facets'][0]['index'])->toBe(['byteStart' => 14, 'byteEnd' => 20])
        ->and($record['embed']['external']['uri'])->toContain('feeding-roses');
});

it('posts to Threads as a container then its publication', function (): void {
    pollen($this);
    $this->outbound()->answer('graph.threads.net', 200, ['id' => 't1']);
    channel('threads', 'Threads', ['access_token' => 'th', 'expires_at' => time() + 86400 * 50], ['account' => '77', 'options' => ['username' => 'garden']]);

    [$share, $sent] = shareOnce($this);

    expect($share['status'])->toBe('sent')
        ->and($sent[0]->url)->toBe('https://graph.threads.net/v1.0/77/threads')
        ->and($sent[0]->form())->toMatchArray(['media_type' => 'TEXT'])
        ->and($sent[1]->url)->toBe('https://graph.threads.net/v1.0/77/threads_publish');
});

it('refuses a server nobody agreed to', function (): void {
    pollen($this);
    $this->outbound();
    channel('mastodon', 'Mastodon', ['token' => 't'], ['server' => 'https://unlisted.example', 'account' => '1']);

    [$share] = shareOnce($this);

    expect($share['status'])->toBe('failed')
        ->and($share['reply'])->toBe('Teme refused to reach this network’s address. Check that it is agreed to in Settings › Pollen.');
});
