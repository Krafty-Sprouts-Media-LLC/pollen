<?php

/**
 * Pollen: the admin notice for channels that need someone.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Status;

use KraftySprouts\Pollen\Doors;
use Teme\Contracts\Status\StatusCheck;
use Teme\Contracts\Status\StatusNotice;
use Throwable;

/**
 * Shows administrators, on every admin page, that Pollen's lock key isn't
 * set, or how many channels must be connected again, until it is fixed.
 *
 * @since 1.0.0
 */
final readonly class ChannelsNeedYou implements StatusCheck
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    public function notice(): ?StatusNotice
    {
        try {
            if (! $this->doors->settings->vault()->ready()) {
                return new StatusNotice('warning', __('Pollen needs a lock key before it can connect channels.'), true);
            }

            $attention = $this->doors->channels->health()['attention'];
        } catch (Throwable) {
            return null;
        }

        if ($attention === 0) {
            return null;
        }

        return new StatusNotice('warning', trans_choice('{1} One Pollen channel needs you: open Pollen’s Channels page.|[2,*] :count Pollen channels need you: open Pollen’s Channels page.', $attention, ['count' => $attention]));
    }
}
