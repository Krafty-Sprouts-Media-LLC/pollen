<?php

/**
 * Pollen: every network Pollen can post to.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

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
     * @since 1.0.0
     */
    public function __construct(Http $http)
    {
        foreach ([new Telegram($http)] as $network) {
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
