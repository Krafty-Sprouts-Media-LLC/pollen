<?php

/**
 * Pollen: a network connected by signing in on the network.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * A network whose channel connects through OAuth with the site owner's own
 * app: the address that asks the person to agree, the exchange of the code
 * it sends back, and the refresh of access that expires.
 *
 * @since 1.0.0
 */
interface OAuthNetwork extends Network
{
    /**
     * Returns the network's address where the person agrees to let Pollen post.
     *
     * @since 1.0.0
     */
    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string;

    /**
     * Exchanges the code the network sent back for credentials.
     *
     * @throws ShareFailed when the network refuses the code
     *
     * @since 1.0.0
     */
    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected;

    /**
     * Returns fresh credentials when access is about to expire and can be renewed, or null when it needn't or can't be.
     *
     * @param  array<string, mixed>  $credentials
     *
     * @throws ShareFailed when the network refuses to renew
     *
     * @since 1.0.0
     */
    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected;

    /**
     * Returns the settings key prefix of the app this network signs in with, such as "facebook".
     *
     * @since 1.0.0
     */
    public function app(): string;
}
