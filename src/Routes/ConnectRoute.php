<?php

/**
 * Pollen: the address that starts a channel's sign-in.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Routes;

use KraftySprouts\Pollen\Connect\Pkce;
use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Networks\OAuthNetwork;
use KraftySprouts\Pollen\Settings;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Routes\PublicRequest;
use Teme\Contracts\Routes\PublicResponse;
use Teme\Contracts\Routes\PublicRoute;
use Teme\Contracts\Settings\SecretReader;
use Teme\Contracts\Settings\SettingsReader;
use Teme\Contracts\Site\SiteInfo;

/**
 * Answers /pollen/connect/{key}, the address a channel's Sign-in column
 * shows: it records a new sign-in with a random state and PKCE verifier, and
 * sends the person to the network to agree. The key is 40 random characters
 * only Pollen's area shows the people who manage it.
 *
 * @since 1.0.0
 */
final readonly class ConnectRoute implements PublicRoute
{
    private Doors $doors;

    /**
     * @since 1.0.0
     */
    public function __construct(SettingsReader $settings, SecretReader $secrets, SiteInfo $site, OutboundHttp $outbound)
    {
        $this->doors = new Doors(new Settings($settings, $secrets), $site, $outbound);
    }

    public function handle(PublicRequest $request): PublicResponse
    {
        $back = $this->doors->site->address().'/admin';
        $channel = $this->doors->channels->byConnectKey((string) ($request->parameters['key'] ?? ''));
        $network = $channel === null ? null : $this->doors->networks->find($channel->network);

        if ($channel === null || ! $network instanceof OAuthNetwork) {
            return Page::answer(404, __('This sign-in address has been used or replaced'), [__('Open Pollen’s Channels page again for the channel’s current address.')], $back);
        }

        $app = $this->doors->settings->app($network->app());

        if (! $app->ready() || ! $this->doors->settings->vault()->ready()) {
            return Page::answer(409, __('Pollen isn’t ready to connect :network', ['network' => $network->label()]), [__('Set Pollen’s lock key and your :network app keys in Settings › Pollen, then try again.', ['network' => $network->label()])], $back);
        }

        $verifier = Pkce::verifier();
        $state = $this->doors->connects->start($channel->id, $verifier);

        return new PublicResponse(302, 'text/plain; charset=utf-8', '', [
            'location' => $network->authorizeUrl($app, $channel, $this->doors->callbackUrl(), $state, Pkce::challenge($verifier)),
            'x-robots-tag' => 'noindex',
        ]);
    }
}
