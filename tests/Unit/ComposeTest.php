<?php

declare(strict_types=1);

use KraftySprouts\Pollen\Compose\Fitter;
use KraftySprouts\Pollen\Compose\Hashtags;
use KraftySprouts\Pollen\Compose\Template;
use KraftySprouts\Pollen\Compose\TiptapText;
use KraftySprouts\Pollen\Compose\Utm;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../bootstrap.php';

uses(TemeTestCase::class);

it('reads plain text and the first image from a Tiptap document', function (): void {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'Spring']]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Roses need '],
            ['type' => 'text', 'text' => 'water', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => '.'],
        ]],
        ['type' => 'image', 'attrs' => ['src' => 'https://cdn.example.com/rose.jpg', 'alt' => 'A rose']],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'And sun.']]],
    ]];

    expect(TiptapText::plain($doc))->toBe("Spring\n\nRoses need water.\n\nAnd sun.")
        ->and(TiptapText::excerpt($doc, 20))->toBe('Spring Roses need…')
        ->and(TiptapText::firstImage($doc))->toBe('https://cdn.example.com/rose.jpg');
});

it('turns terms into hashtags, keeping only letters and digits', function (): void {
    expect(Hashtags::from(['garden-care', 'roses', '2026 plans', 'été'], 3))->toBe(['#GardenCare', '#Roses', '#2026Plans'])
        ->and(Hashtags::from([], 5))->toBe([]);
});

it('fills a template with variables and leaves unknown ones out', function (): void {
    $text = Template::render("{title}\n\n{excerpt}\n{hashtags} {link} {nope}", [
        'title' => 'Feeding roses',
        'excerpt' => 'How and when.',
        'hashtags' => '#Roses',
        'link' => 'https://example.com/roses/',
    ]);

    expect($text)->toBe("Feeding roses\n\nHow and when.\n#Roses https://example.com/roses/");
});

it('tags a link with UTM values from patterns, keeping its own query', function (): void {
    $utm = new Utm(source: '{network}', medium: 'social', campaign: '{slug}');

    expect($utm->apply('https://example.com/roses/?a=1#top', ['network' => 'mastodon', 'slug' => 'feeding-roses']))
        ->toBe('https://example.com/roses/?a=1&utm_source=mastodon&utm_medium=social&utm_campaign=feeding-roses#top')
        ->and((new Utm('', '', ''))->apply('https://example.com/x', []))->toBe('https://example.com/x');
});

it('fits text to a network, counting a link as the network counts it', function (): void {
    $long = str_repeat('word ', 80).'https://example.com/a-very-long-address-that-goes-on-and-on';

    $fitted = Fitter::fit($long, 280, linkWeight: 23);

    expect(Fitter::length($fitted, 23))->toBeLessThanOrEqual(280)
        ->and($fitted)->toEndWith('https://example.com/a-very-long-address-that-goes-on-and-on')
        ->and($fitted)->toContain('…')
        ->and(Fitter::fit('Short and sweet', 280))->toBe('Short and sweet');
});
