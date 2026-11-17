<?php

/**
 * Pollen: the address networks send people back to after signing in.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Routes;

use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Networks\OAuthNetwork;
use KraftySprouts\Pollen\Networks\ShareFailed;
use KraftySprouts\Pollen\Settings;
use KraftySprouts\Pollen\Store\VaultLocked;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Routes\PublicRequest;
use Teme\Contracts\Routes\PublicResponse;
use Teme\Contracts\Routes\PublicRoute;
use Teme\Contracts\Settings\SecretReader;
use Teme\Contracts\Settings\SettingsReader;
use Teme\Contracts\Site\SiteInfo;

/**
 * Answers /pollen/callback: takes the state and code a network sends back,
 * refuses a state it didn't start or that expired, exchanges the code for
 * the channel's credentials, seals them, and sends any shares that waited
 * for the channel.
 *
 * @since 1.0.0
 */
final readonly class CallbackRoute implements PublicRoute
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
        $state = is_string($request->query['state'] ?? null) ? $request->query['state'] : '';
        $code = is_string($request->query['code'] ?? null) ? $request->query['code'] : '';
        $started = $state === '' ? null : $this->doors->connects->finish($state);
        $channel = $started === null ? null : $this->doors->channels->find($started['channel_id']);
        $network = $channel === null ? null : $this->doors->networks->find($channel->network);

        if ($channel === null || ! $network instanceof OAuthNetwork) {
            return Page::answer(400, __('This sign-in has expired'), [__('Start again from the address in Pollen’s Channels page. A sign-in must be finished within half an hour.')], $back);
        }

        if ($code === '') {
            $said = is_string($request->query['error_description'] ?? null) ? $request->query['error_description'] : (is_string($request->query['error'] ?? null) ? $request->query['error'] : '');

            return Page::answer(400, __(':network didn’t connect', ['network' => $network->label()]), [__('The network said: :why', ['why' => mb_substr($said !== '' ? $said : __('nothing'), 0, 200)])], $back);
        }

        try {
            $connected = $network->exchange($this->doors->settings->app($network->app()), $channel, $code, $started['verifier'], $this->doors->callbackUrl());
            $this->doors->channels->connected($channel, $connected, $this->doors->settings->vault());
        } catch (ShareFailed|VaultLocked $failed) {
            return Page::answer(400, __(':network didn’t connect', ['network' => $network->label()]), [$failed->getMessage()], $back);
        }

        if ($connected->mustChoose()) {
            return Page::answer(200, __('Signed in to :network', ['network' => $network->label()]), [
                __('This sign-in reaches more than one account. Type the one “:name” posts to in its Account field, and save:', ['name' => $channel->name]),
                implode(', ', array_map(fn (array $c): string => $c['name'].' ('.$c['id'].')', array_slice($connected->choices, 0, 20))),
            ], $back);
        }

        $released = $this->doors->shares->release($channel->id);

        return Page::answer(200, __('“:name” is connected', ['name' => $channel->name]), [
            __('Pollen posts to :account on :network.', ['account' => $connected->accountName !== '' ? $connected->accountName : $connected->accountId, 'network' => $network->label()]),
            $released > 0 ? trans_choice('{1} One waiting share goes within a minute.|[2,*] :count waiting shares go within a minute.', $released, ['count' => $released]) : __('You can close this tab.'),
        ], $back);
    }
}
