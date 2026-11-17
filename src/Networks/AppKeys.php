<?php

/**
 * Pollen: the site owner's own app keys on a network.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * The ID and secret of the app the site owner made on a network, read from
 * Pollen's settings when a channel connects or its access is renewed.
 *
 * @since 1.0.0
 */
final readonly class AppKeys
{
    /**
     * @since 1.0.0
     */
    public function __construct(
        public string $id,
        public string $secret,
    ) {}

    /**
     * Returns whether both keys are set.
     *
     * @since 1.0.0
     */
    public function ready(): bool
    {
        return trim($this->id) !== '' && trim($this->secret) !== '';
    }

    /**
     * Returns the HTTP Basic header value for the two keys.
     *
     * @since 1.0.0
     */
    public function basic(): string
    {
        return 'Basic '.base64_encode($this->id.':'.$this->secret);
    }
}
