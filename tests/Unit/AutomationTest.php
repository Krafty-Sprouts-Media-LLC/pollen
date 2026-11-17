<?php

declare(strict_types=1);

use KraftySprouts\Pollen\Automations\Automation;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../bootstrap.php';

uses(TemeTestCase::class);

function automation(array $settings, string $kind = Automation::PUBLISH): Automation
{
    return new Automation('a1', 'Test', $kind, true, $settings, null, 0, '1');
}

it('matches items by type, category, tag and exclusions', function (): void {
    $rule = automation(['types' => ['post'], 'categories' => 'garden, Roses', 'exclude_terms' => 'draft-ideas', 'exclude_items' => "01abc\nhttps://example.com/skip/"]);

    expect($rule->matches('01x', 'post', 'https://example.com/a/', ['category' => ['roses']]))->toBeTrue()
        ->and($rule->matches('01x', 'page', '', ['category' => ['roses']]))->toBeFalse()
        ->and($rule->matches('01x', 'post', '', ['category' => ['lawns']]))->toBeFalse()
        ->and($rule->matches('01x', 'post', '', ['category' => ['roses'], 'tag' => ['draft-ideas']]))->toBeFalse()
        ->and($rule->matches('01abc', 'post', '', ['category' => ['roses']]))->toBeFalse()
        ->and($rule->matches('01y', 'post', 'https://example.com/skip/', ['category' => ['roses']]))->toBeFalse()
        ->and(automation([])->matches('01z', 'post', '', []))->toBeTrue();
});

it('times the next re-share inside its days and hours', function (): void {
    $plan = automation(['every' => '120', 'days' => ['1', '2', '3', '4', '5'], 'from_hour' => 9, 'to_hour' => 17], Automation::RESHARE);
    $zone = new DateTimeZone('UTC');

    // Friday 16:30 plus two hours is 18:30, past the last hour, so Monday at 9.
    $next = $plan->nextRun(new DateTimeImmutable('2026-11-20 16:30', $zone), $zone);

    expect($next->format('D H:i'))->toBe('Mon 09:00')
        ->and($plan->nextRun(new DateTimeImmutable('2026-11-17 10:00', $zone), $zone)->format('D H:i'))->toBe('Tue 12:00');
});
