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
use KraftySprouts\Pollen\Compose\Fitter;
use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Hooks\ShareSent;
use KraftySprouts\Pollen\Hooks\ShareText;
use KraftySprouts\Pollen\Images\ImagePrep;
use KraftySprouts\Pollen\Networks\Limits;
use KraftySprouts\Pollen\Networks\OAuthNetwork;
use KraftySprouts\Pollen\Networks\Post;
use KraftySprouts\Pollen\Networks\ShareFailed;
use KraftySprouts\Pollen\Store\Shares;
use KraftySprouts\Pollen\Store\VaultLocked;
use Teme\Contracts\Content\ReadRefused;
use Throwable;

/**
 * Sends one planned share inside the send-share job: checks its channel,
 * renews the channel's access when it is about to expire, writes the post,
 * prepares the image, lets other plugins change the words, and records how
 * the network answered. A failure worth retrying is thrown back, so Teme
 * tries the job again after its backoff; the last try marks it failed.
 *
 * @since 1.0.0
 */
final readonly class Sender
{
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
    public function send(string $shareId, string $personId, int $attempt, int $tries): void
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

            if ($network instanceof OAuthNetwork) {
                $renewed = $network->refresh($this->doors->settings->app($network->app()), $channel, $credentials);

                if ($renewed !== null) {
                    $this->doors->channels->connected($channel, $renewed, $this->doors->settings->vault());
                    $credentials = $renewed->credentials;
                    $channel = $this->doors->channels->find($channel->id) ?? $channel;
                }
            }

            $post = $this->post($share, $channel, $network, $personId);
            $sent = $network->publish($channel, $credentials, $post);
        } catch (VaultLocked $locked) {
            $this->doors->shares->stopped($shareId, Shares::HELD, $locked->getMessage());

            return;
        } catch (ReadRefused $refused) {
            $this->finalFailure($share, $channel, $refused->getMessage(), $personId);

            return;
        } catch (ShareFailed $failed) {
            $this->failed($share, $channel, $failed, $attempt, $tries, $personId);

            return;
        }

        $this->doors->shares->sent($shareId, $sent->remoteId, $sent->url, $sent->note);
        $this->doors->channels->succeeded($channel->id);

        if (is_string($share['item_id'] ?? null)) {
            $this->doors->items->shared($share['item_id']);
        }

        $this->tell($share, $network->key(), true, $sent->url);
    }

    /**
     * Returns the post for a share: written now from its item, its message and its channel.
     *
     * @param  array<string, mixed>  $share
     *
     * @throws ReadRefused when the person can't read the item
     * @throws ShareFailed
     */
    private function post(array $share, Channel $channel, \KraftySprouts\Pollen\Networks\Network $network, string $personId): Post
    {
        $item = null;
        $itemId = $share['item_id'] ?? null;

        if (is_string($itemId) && $itemId !== '' && $this->doors->content !== null) {
            $item = ItemFacts::read($this->doors->content, $this->doors->peopleReader, $itemId, $personId);

            if ($item->status !== 'published' || $item->url === '') {
                throw ShareFailed::final(__('The item isn’t published, so there is nothing to share.'));
            }
        }

        $image = $this->image($share, $item, $network->limits());
        $automation = is_string($share['automation_id'] ?? null) ? $this->doors->automations->find($share['automation_id']) : null;
        $composer = new Composer($this->doors->settings, $this->doors->site->name());
        $post = $composer->compose(
            $channel,
            $network,
            $item,
            (string) ($share['message'] ?? ''),
            $automation?->text('template') ?? '',
            $image,
            (string) ($share['first_comment'] ?? ''),
            (string) $share['id'],
        );

        if ($post->imageUrl !== '') {
            $post = new Post($post->text, $post->link, $post->linkMode, (new ImagePrep($this->doors))->prepare($post->imageUrl, $network), $post->firstComment, $post->title, $post->excerpt, $post->extra, $post->key, $post->language);
        }

        if ($network->limits()->image === Limits::IMAGE_REQUIRED && $post->imageUrl === '') {
            throw ShareFailed::final(__(':network needs an image, and this share has none.', ['network' => $network->label()]));
        }

        if ($this->doors->hooks !== null) {
            $changed = $this->doors->hooks->transform(new ShareText($post->text, $network->key(), $channel->id, $item?->id));

            if (is_string($changed) && trim($changed) !== '' && $changed !== $post->text) {
                $limits = $network->limits();
                $post = new Post(Fitter::fit($changed, $limits->maxLength, $limits->linkWeight), $post->link, $post->linkMode, $post->imageUrl, $post->firstComment, $post->title, $post->excerpt, $post->extra, $post->key, $post->language);
            }
        }

        $this->doors->shares->written((string) $share['id'], $post->text, $post->imageUrl, $item->title ?? '', $item->url ?? '');

        return $post;
    }

    /**
     * Returns the image a share goes with: the one chosen for it, else the article's first, as the settings say.
     *
     * @param  array<string, mixed>  $share
     */
    private function image(array $share, ?ItemFacts $item, Limits $limits): string
    {
        $source = $this->doors->settings->string('image_source');

        if ($limits->image === Limits::IMAGE_NONE || $source === 'none') {
            return '';
        }

        $chosen = trim((string) ($share['image_url'] ?? ''));

        if ($chosen !== '') {
            return $chosen;
        }

        return $source === 'chosen' || $item === null ? '' : $item->firstImage;
    }

    /**
     * Records a failure and decides what follows: try again, hold for a new sign-in, or stop.
     *
     * @param  array<string, mixed>  $share
     *
     * @throws ShareFailed when Teme should try the job again
     */
    private function failed(array $share, Channel $channel, ShareFailed $failed, int $attempt, int $tries, string $personId): void
    {
        $id = (string) $share['id'];

        if ($failed->kind === ShareFailed::RECONNECT) {
            $this->doors->shares->stopped($id, Shares::HELD, $failed->getMessage());

            if ($this->doors->channels->needsReconnect($channel, $failed->getMessage())) {
                (new Alerts($this->doors))->reconnect($channel, $failed->getMessage());
            }

            return;
        }

        if ($failed->kind === ShareFailed::RETRY && $attempt < $tries) {
            $this->doors->shares->stopped($id, Shares::SENDING, __('Try :n failed: :reason', ['n' => $attempt, 'reason' => $failed->getMessage()]));

            throw $failed;
        }

        $this->finalFailure($share, $channel, $failed->getMessage(), $personId);
    }

    /**
     * Marks a share failed for good, counts it against its channel, and tells whoever must know.
     *
     * @param  array<string, mixed>  $share
     */
    private function finalFailure(array $share, Channel $channel, string $why, string $personId): void
    {
        $this->doors->shares->stopped((string) $share['id'], Shares::FAILED, $why);
        $count = $this->doors->channels->failed($channel->id, $why);
        $alerts = new Alerts($this->doors);

        if ($count === max(1, $this->doors->settings->int('failure_limit'))) {
            $alerts->failing($channel, $count, $why);
        }

        if (($share['requested_by'] ?? '') === $personId && $personId !== '') {
            $alerts->shareFailed($personId, $share, $channel, $why);
        }

        $this->tell($share, $channel->network, false, '');
    }

    /**
     * Tells other plugins how a share went.
     *
     * @param  array<string, mixed>  $share
     */
    private function tell(array $share, string $network, bool $sent, string $url): void
    {
        if ($this->doors->hooks === null) {
            return;
        }

        try {
            $this->doors->hooks->notify(new ShareSent((string) $share['id'], $network, is_string($share['item_id'] ?? null) ? $share['item_id'] : null, $sent, $url, is_string($share['request_key'] ?? null) ? $share['request_key'] : null));
        } catch (Throwable) {
            // A listener's failure never undoes a share that went.
        }
    }
}
