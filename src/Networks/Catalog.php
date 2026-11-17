<?php

/**
 * Pollen: every network Pollen can post to.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Networks\Meta\FacebookPages;
use KraftySprouts\Pollen\Networks\Meta\Instagram;

/**
 * The networks Pollen posts to, by key, each built once with the outbound
 * door. A channel names its network by key; an unknown key has no network.
 *
 * @since 1.0.0
 */
final class Catalog
{
    /** @var array<string, Network> */
    private array $networks = [];

    /**
     * @param  (callable(string): AppKeys)|null  $apps  the site owner's app keys by settings prefix, for networks that sign each request
     *
     * @since 1.0.0
     */
    public function __construct(Http $http, ?callable $apps = null)
    {
        $all = [
            new FacebookPages($http),
            new Instagram($http),
            new X($http),
            new LinkedIn($http),
            new LinkedIn($http, page: true),
            new Pinterest($http),
            new Telegram($http),
            new Bluesky($http),
            new Threads($http),
            new Mastodon($http),
            new Reddit($http),
            new Discord($http),
            new GoogleBusiness($http),
            new Tumblr($http),
            new Vk($http),
            (new Plurk($http))->withApp(fn (): AppKeys => $apps === null ? new AppKeys('', '') : $apps('plurk')),
            new Blogger($http),
            new WordPress($http),
        ];

        foreach ($all as $network) {
            $this->networks[$network->key()] = $network;
        }
    }

    /**
     * Returns the network with this key, or null.
     *
     * @since 1.0.0
     */
    public function find(string $key): ?Network
    {
        return $this->networks[$key] ?? null;
    }

    /**
     * Returns every network, by key.
     *
     * @return array<string, Network>
     *
     * @since 1.0.0
     */
    public function all(): array
    {
        return $this->networks;
    }
}
