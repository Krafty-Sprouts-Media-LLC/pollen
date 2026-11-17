<?php

declare(strict_types=1);

use KraftySprouts\Pollen\Connect\OAuth1;
use KraftySprouts\Pollen\Connect\Pkce;
use KraftySprouts\Pollen\Store\Vault;
use KraftySprouts\Pollen\Store\VaultLocked;
use Teme\Testing\TemeTestCase;

require_once __DIR__.'/../bootstrap.php';

uses(TemeTestCase::class);

it('seals tokens so only the same lock key opens them', function (): void {
    $vault = new Vault('a long lock key the administrator typed');
    $box = $vault->seal(['access_token' => 'tok-123']);

    expect($box)->not->toContain('tok-123')
        ->and($vault->open($box))->toBe(['access_token' => 'tok-123'])
        ->and(fn () => (new Vault('another key entirely, also long'))->open($box))->toThrow(VaultLocked::class)
        ->and(fn () => (new Vault(''))->seal([]))->toThrow(VaultLocked::class);
});

it('signs an OAuth 1.0a request as RFC 5849 describes', function (): void {
    // The worked example from RFC 5849, section 3.4.1.
    $header = OAuth1::header(
        'POST',
        'http://example.com/request',
        ['b5' => '=%3D', 'a3' => 'a', 'c@' => '', 'a2' => 'r b', 'c2' => '', 'a3 ' => '2 q'],
        consumerKey: '9djdj82h48djs9d2',
        consumerSecret: 'j49sk3j29djd',
        token: 'kkk9d7dh3k39sjv7',
        tokenSecret: 'dh893hdasih9',
        nonce: '7d8f3e4a',
        timestamp: 137131201,
    );

    expect($header)->toStartWith('OAuth ')
        ->and($header)->toContain('oauth_signature_method="HMAC-SHA1"')
        ->and($header)->toContain('oauth_consumer_key="9djdj82h48djs9d2"');
});

it('signs the X example request to the documented signature', function (): void {
    // X's own documented example of creating a signature.
    $signature = OAuth1::signature(
        'POST',
        'https://api.twitter.com/1.1/statuses/update.json',
        [
            'include_entities' => 'true',
            'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!',
            'oauth_consumer_key' => 'xvz1evFS4wEEPTGEFPHBog',
            'oauth_nonce' => 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => '1318622958',
            'oauth_token' => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
            'oauth_version' => '1.0',
        ],
        'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw',
        'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE',
    );

    expect($signature)->toBe('hCtSmYh+iHYCEqBWrE7C7hYmtUk=');
});

it('makes a PKCE verifier and its S256 challenge', function (): void {
    $verifier = Pkce::verifier();

    expect(strlen($verifier))->toBeGreaterThanOrEqual(43)
        ->and(Pkce::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'))->toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
});
