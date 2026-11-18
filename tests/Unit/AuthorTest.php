<?php

declare(strict_types=1);

use KraftySprouts\Pollen\Compose\ItemFacts;
use Teme\Contracts\Content\ItemRef;
use Teme\Contracts\Content\ItemSnapshot;
use Teme\Contracts\People\PeopleReader;
use Teme\Contracts\People\PersonPage;
use Teme\Contracts\People\PersonRef;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../bootstrap.php';

uses(TemeTestCase::class);

/** A people door that knows three writers. */
function writers(): PeopleReader
{
    return new class implements PeopleReader
    {
        public function writers(?string $after = null, int $limit = 100): PersonPage
        {
            return new PersonPage([new PersonRef('1', 'Ada'), new PersonRef('2', 'Bo'), new PersonRef('3', 'Cy')], null);
        }
    };
}

function snapshot(array $credits): ItemSnapshot
{
    return new ItemSnapshot(new ItemRef('01x', 'post', 'Pruning', 'published', 'https://example.com/pruning/', 'en'), 'r1', 'pruning', ['type' => 'doc', 'content' => []], [], [], $credits, []);
}

it('names the first credited person as the author, not all of them', function (): void {
    expect(ItemFacts::fromSnapshot(snapshot(['2', '3']), writers(), '1')->author)->toBe('Bo');
});

it('falls back to the person who published it when the item credits nobody', function (): void {
    expect(ItemFacts::fromSnapshot(snapshot([]), writers(), '3')->author)->toBe('Cy');
});

it('names nobody when it can’t tell', function (): void {
    expect(ItemFacts::fromSnapshot(snapshot([]), writers(), '')->author)->toBe('');
});
