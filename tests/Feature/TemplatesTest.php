<?php

declare(strict_types=1);

use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../Support.php';

uses(TemeTestCase::class);

/**
 * Publishes an item, runs the minute's pass with Telegram answering, and returns the text Telegram was sent.
 *
 * @param  array<string, mixed>  $settings
 * @param  array<string, mixed>  $channel
 */
function sharedText(object $test, array $settings, array $channel = [], string $title = 'Feeding roses', ?object $by = null): string
{
    $plugin = pollen($test, $settings);
    $outbound = $test->outbound()->answer('api.telegram.org', 200, ['ok' => true, 'result' => ['message_id' => 1]]);
    channel('telegram', 'Garden club', ['token' => '123456:TEST-token-for-pollen-tests-only'], ['account' => '-1001', ...$channel]);

    $test->publish($title, 'post', $by);
    $plugin->runJob('dispatch');

    return (string) $outbound->sent('api.telegram.org')[0]->json()['text'];
}

it('writes a share with Pollen’s own text', function (): void {
    $text = sharedText($this, ['template' => 'New: {title} on {site}']);

    expect($text)->toStartWith('New: Feeding roses on ')->and($text)->not->toContain('{');
});

it('writes a network’s own text when Settings has one for it', function (): void {
    $text = sharedText($this, [
        'template' => 'Everywhere else: {title}',
        'templates' => [['network' => 'telegram', 'text' => 'On Telegram: {title}']],
    ]);

    expect($text)->toStartWith('On Telegram: Feeding roses');
});

it('writes a channel’s own text before the network’s', function (): void {
    $text = sharedText($this, [
        'templates' => [['network' => 'telegram', 'text' => 'Network: {title}']],
    ], ['options' => ['template' => 'Channel: {title}']]);

    expect($text)->toStartWith('Channel: Feeding roses');
});

it('names the writer for {author}', function (): void {
    $writer = $this->person('author');

    expect(sharedText($this, ['template' => '{title}, by {author}'], [], 'Pruning', $writer))->toStartWith('Pruning, by '.$writer->name);
});

it('tags the shared link with the campaign tags set', function (): void {
    $text = sharedText($this, ['template' => '{link}', 'utm' => true, 'utm_source' => '{network}', 'utm_medium' => 'social', 'utm_campaign' => '{slug}']);

    expect($text)->toContain('?utm_source=telegram&utm_medium=social&utm_campaign=feeding-roses');
});

it('adds the link when the text leaves it out', function (): void {
    expect(sharedText($this, ['template' => '{title}']))->toContain('http');
});

it('leaves the link out for a channel that says none', function (): void {
    expect(sharedText($this, ['template' => '{title} {link}'], ['options' => ['link' => 'none']]))->toBe('Feeding roses');
});

it('fits a long text to the network’s limit and keeps the link whole', function (): void {
    $text = sharedText($this, ['template' => str_repeat('{title} ', 9).'{link}'], [], str_repeat('Roses ', 80));

    expect(mb_strlen($text))->toBeLessThanOrEqual(4096)
        ->and($text)->toContain('…')
        ->and($text)->toMatch('#https?://\S+$#');
});
