<?php

/**
 * Pollen: the doors Teme hands Pollen, and Pollen's own stores.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen;

use KraftySprouts\Pollen\Networks\AppKeys;
use KraftySprouts\Pollen\Networks\Catalog;
use KraftySprouts\Pollen\Networks\Http;
use KraftySprouts\Pollen\Store\Automations;
use KraftySprouts\Pollen\Store\Channels;
use KraftySprouts\Pollen\Store\Connects;
use KraftySprouts\Pollen\Store\Images;
use KraftySprouts\Pollen\Store\Items;
use KraftySprouts\Pollen\Store\People;
use KraftySprouts\Pollen\Store\Shares;
use Teme\Contracts\Ai\SiteAi;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Hooks\HookSender;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Jobs\Jobs;
use Teme\Contracts\Notices\Notices;
use Teme\Contracts\People\PeopleReader;
use Teme\Contracts\Site\SiteInfo;

/**
 * Everything Pollen's parts work with, in one place: the doors Teme built
 * Pollen with, Pollen's settings, the networks, and the stores over its own
 * tables. A door Pollen wasn't given is null, as on a route's handler.
 *
 * @since 1.0.0
 */
final class Doors
{
    public readonly Http $http;

    public readonly Catalog $networks;

    public readonly Channels $channels;

    public readonly Shares $shares;

    public readonly Automations $automations;

    public readonly Items $items;

    public readonly Connects $connects;

    public readonly People $people;

    public readonly Images $images;

    /**
     * @since 1.0.0
     */
    public function __construct(
        public readonly Settings $settings,
        public readonly SiteInfo $site,
        OutboundHttp $outbound,
        public readonly ?Jobs $jobs = null,
        public readonly ?ContentReader $content = null,
        public readonly ?PeopleReader $peopleReader = null,
        public readonly ?SiteAi $ai = null,
        public readonly ?Notices $notices = null,
        public readonly ?HookSender $hooks = null,
    ) {
        $this->http = new Http($outbound);
        $this->networks = new Catalog($this->http, fn (string $prefix): AppKeys => $settings->app($prefix));
        $this->channels = new Channels;
        $this->shares = new Shares;
        $this->automations = new Automations;
        $this->items = new Items;
        $this->connects = new Connects;
        $this->people = new People;
        $this->images = new Images;
    }

    /**
     * Returns the address networks send people back to after they sign in.
     *
     * @since 1.0.0
     */
    public function callbackUrl(): string
    {
        return $this->site->address().'/pollen/callback';
    }

    /**
     * Returns the address that starts a channel's sign-in.
     *
     * @since 1.0.0
     */
    public function connectUrl(string $key): string
    {
        return $this->site->address().'/pollen/connect/'.$key;
    }
}
