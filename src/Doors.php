<?php

/**
 * Pollen: the doors Teme hands Pollen, and Pollen's own stores.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen;

use KraftySprouts\Pollen\Networks\Catalog;
use KraftySprouts\Pollen\Networks\Http;
use KraftySprouts\Pollen\Store\Channels;
use KraftySprouts\Pollen\Store\Items;
use KraftySprouts\Pollen\Store\Shares;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Jobs\Jobs;
use Teme\Contracts\Site\SiteInfo;

/**
 * Everything Pollen's parts work with, in one place: the doors Teme built
 * Pollen with, Pollen's settings, the networks, and the stores over its own
 * tables. A door Pollen wasn't given is null.
 *
 * @since 1.0.0
 */
final class Doors
{
    public readonly Http $http;

    public readonly Catalog $networks;

    public readonly Channels $channels;

    public readonly Shares $shares;

    public readonly Items $items;

    /**
     * @since 1.0.0
     */
    public function __construct(
        public readonly Settings $settings,
        public readonly SiteInfo $site,
        OutboundHttp $outbound,
        public readonly ?Jobs $jobs = null,
        public readonly ?ContentReader $content = null,
    ) {
        $this->http = new Http($outbound);
        $this->networks = new Catalog($this->http);
        $this->channels = new Channels;
        $this->shares = new Shares;
        $this->items = new Items;
    }
}
