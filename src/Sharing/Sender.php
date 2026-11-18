<?php

/**
 * Pollen: sending one planned share.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Compose\Composer;
use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Networks\Limits;
use KraftySprouts\Pollen\Networks\Network;
use KraftySprouts\Pollen\Networks\Post;
use KraftySprouts\Pollen\Networks\ShareFailed;
use KraftySprouts\Pollen\Store\Shares;
use KraftySprouts\Pollen\Store\VaultLocked;
use Teme\Contracts\Content\ReadRefused;

/**
 * Sends one planned share inside the send-share job: checks its channel,
 * writes the post from the item as it is now, and records how the network
 * answered. A failure worth retrying is thrown back, so Teme tries the job
 * again after its backoff; the last try marks it failed. A channel whose
 * sign-in no longer works holds its shares until it is connected again.
 *
 * @since 1.0.0
 */
final readonly class Sender
{
    /** How many times Teme tries a send-share job, as the manifest declares. */
    public const TRIES = 3;

    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Sends the share, acting for $personId.
     *
     * @throws ShareFailed when a later try may still succeed
     *
     * @since 1.0.0
     */
    public function send(string $shareId, string $personId, int $attempt): void
    {
        $share = $this->doors->shares->find($shareId);

        if ($share === null) {
            return;
        }

        $resuming = $share['status'] === Shares::SENDING && $attempt > 1;

        if (! $resuming && ! $this->doors->shares->claim($shareId)) {
            return;
        }

        if ($this->doors->settings->bool('pause')) {
            $this->doors->shares->again($shareId, now()->addMinutes(5), __('Sharing is paused in Settings › Pollen.'));

            return;
        }

        $channel = $this->doors->channels->find((string) $share['channel_id']);

        if ($channel === null) {
            $this->doors->shares->stopped($shareId, Shares::FAILED, __('Its channel was deleted.'));

            return;
        }

        if (! $channel->ready()) {
            $this->doors->shares->stopped($shareId, Shares::HELD, $channel->active ? __('Waiting for the channel to be connected.') : __('Waiting for the channel to be switched on.'));

            return;
        }

        $network = $this->doors->networks->find($channel->network);

        if ($network === null) {
            $this->doors->shares->stopped($shareId, Shares::FAILED, __('Pollen doesn’t know this channel’s network.'));

            return;
        }

        try {
            $credentials = $this->doors->settings->vault()->open($channel->sealed);
            $post = $this->post($share, $channel, $network, $personId);
            $sent = $network->publish($channel, $credentials, $post);
        } catch (VaultLocked $locked) {
            $this->doors->shares->stopped($shareId, Shares::HELD, $locked->getMessage());

            return;
        } catch (ReadRefused $refused) {
            $this->finalFailure($shareId, $channel, $refused->getMessage());

            return;
        } catch (ShareFailed $failed) {
            $this->failed($shareId, $channel, $failed, $attempt);

            return;
        }

        $this->doors->shares->sent($shareId, $sent->remoteId, $sent->url, $sent->note);
        $this->doors->channels->succeeded($channel->id);

        if (is_string($share['item_id'] ?? null)) {
            $this->doors->items->shared($share['item_id']);
        }
    }

    /**
     * Returns the post for a share, written now from its item, its message and its channel.
     *
     * @param  array<string, mixed>  $share
     *
     * @throws ReadRefused when the person can't read the item
     * @throws ShareFailed
     */
    private function post(array $share, Channel $channel, Network $network, string $personId): Post
    {
        $item = null;
        $itemId = $share['item_id'] ?? null;

        if (is_string($itemId) && $itemId !== '' && $this->doors->content !== null) {
            $item = ItemFacts::read($this->doors->content, $this->doors->peopleReader, $itemId, $personId, $this->doors->items->actor($itemId));

            if ($item->status !== 'published' || $item->url === '') {
                throw ShareFailed::final(__('The item isn’t published, so there is nothing to share.'));
            }
        }

        $image = trim((string) ($share['image_url'] ?? ''));

        if ($image === '' && $item !== null) {
            $image = $item->firstImage;
        }

        $post = (new Composer($this->doors->settings, $this->doors->site->name()))->compose(
            $channel,
            $network,
            $item,
            (string) ($share['message'] ?? ''),
            '',
            $network->limits()->image === Limits::IMAGE_NONE ? '' : $image,
            '',
            (string) $share['id'],
        );

        if ($network->limits()->image === Limits::IMAGE_REQUIRED && $post->imageUrl === '') {
            throw ShareFailed::final(__(':network needs an image, and this share has none.', ['network' => $network->label()]));
        }

        $this->doors->shares->written((string) $share['id'], $post->text, $post->imageUrl, $item->title ?? '', $item->url ?? '');

        return $post;
    }

    /**
     * Records a failure and decides what follows: try again, hold for a new sign-in, or stop.
     *
     * @throws ShareFailed when Teme should try the job again
     */
    private function failed(string $shareId, Channel $channel, ShareFailed $failed, int $attempt): void
    {
        if ($failed->kind === ShareFailed::RECONNECT) {
            $this->doors->shares->stopped($shareId, Shares::HELD, $failed->getMessage());
            $this->doors->channels->needsReconnect($channel, $failed->getMessage());

            return;
        }

        if ($failed->kind === ShareFailed::RETRY && $attempt < self::TRIES) {
            $this->doors->shares->stopped($shareId, Shares::SENDING, __('Try :n failed: :reason', ['n' => $attempt, 'reason' => $failed->getMessage()]));

            throw $failed;
        }

        $this->finalFailure($shareId, $channel, $failed->getMessage());
    }

    /**
     * Marks a share failed for good and counts it against its channel.
     */
    private function finalFailure(string $shareId, Channel $channel, string $why): void
    {
        $this->doors->shares->stopped($shareId, Shares::FAILED, $why);
        $this->doors->channels->failed($channel->id, $why);
    }
}
