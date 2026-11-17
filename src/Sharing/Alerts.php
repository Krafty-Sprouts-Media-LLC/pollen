<?php

/**
 * Pollen: telling people when something needs them.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Doors;
use Teme\Contracts\Notices\NoticeMessage;
use Throwable;

/**
 * Sends Pollen's notices through Teme: to the people who manage Pollen when
 * a channel must be connected again or keeps failing, and to a writer when a
 * share they planned failed. Teme decides who of those named may receive
 * each, by mail or inbox as they chose, and limits how many go out.
 *
 * @since 1.0.0
 */
final readonly class Alerts
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Tells the people who manage Pollen a channel must be connected again.
     *
     * @since 1.0.0
     */
    public function reconnect(Channel $channel, string $why): void
    {
        $this->toManagers('reconnect', new NoticeMessage(
            __('Pollen: connect “:name” again', ['name' => $channel->name]),
            [
                __(':network no longer accepts the sign-in of the channel “:name”, so its shares are waiting.', ['network' => $channel->network, 'name' => $channel->name]),
                __('The network said: :why', ['why' => mb_substr($why, 0, 300)]),
                __('Open Pollen’s Channels page and use the address in its Sign-in column. The waiting shares go as soon as it is connected.'),
            ],
            __('Open Pollen'),
            'plugins.area.show',
            ['code' => 'pollen'],
        ));
    }

    /**
     * Tells the people who manage Pollen a channel failed several times in a row.
     *
     * @since 1.0.0
     */
    public function failing(Channel $channel, int $count, string $why): void
    {
        $this->toManagers('failing', new NoticeMessage(
            __('Pollen: “:name” keeps failing', ['name' => $channel->name]),
            [
                __('The last :count shares to the channel “:name” failed.', ['count' => $count, 'name' => $channel->name]),
                __('The last time, the network said: :why', ['why' => mb_substr($why, 0, 300)]),
                __('Pollen’s Planner page lists each failure; Duplicate on a row sends it again.'),
            ],
            __('Open Pollen'),
            'plugins.area.show',
            ['code' => 'pollen'],
        ));
    }

    /**
     * Tells a writer a share they planned failed.
     *
     * @param  array<string, mixed>  $share
     *
     * @since 1.0.0
     */
    public function shareFailed(string $personId, array $share, Channel $channel, string $why): void
    {
        $this->send('my_share_failed', [$personId], new NoticeMessage(
            __('Pollen couldn’t share to “:name”', ['name' => $channel->name]),
            [
                __('Your share of “:title” to “:name” failed.', ['title' => mb_substr((string) ($share['item_title'] ?? ''), 0, 200) ?: __('your message'), 'name' => $channel->name]),
                __('The network said: :why', ['why' => mb_substr($why, 0, 300)]),
            ],
        ));
    }

    /**
     * Sends a notice of a kind meant for the people who manage Pollen; Teme passes over everyone else named.
     */
    private function toManagers(string $kind, NoticeMessage $message): void
    {
        $ids = [];

        if ($this->doors->peopleReader !== null) {
            $after = null;

            for ($page = 0; $page < 5; $page++) {
                $found = $this->doors->peopleReader->writers($after, 200);

                foreach ($found->people as $person) {
                    $ids[] = $person->id;
                }

                if (($after = $found->next) === null) {
                    break;
                }
            }
        }

        $this->send($kind, array_values(array_unique($ids)), $message);
    }

    /**
     * Sends, and never lets a notice that can't go stop the work.
     *
     * @param  list<string>  $ids
     */
    private function send(string $kind, array $ids, NoticeMessage $message): void
    {
        if ($this->doors->notices === null || $ids === []) {
            return;
        }

        try {
            $this->doors->notices->send($kind, $ids, $message);
        } catch (Throwable) {
            try {
                // Without the button, in case the area's address isn't one notices may lead to.
                $this->doors->notices->send($kind, $ids, new NoticeMessage($message->subject, $message->lines));
            } catch (Throwable) {
                // A notice that can't go is not a failed share.
            }
        }
    }
}
