<?php

/**
 * Pollen: the plugin Teme starts.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen;

use DateTimeImmutable;
use DateTimeZone;
use KraftySprouts\Pollen\Area\PollenArea;
use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Editor\SharePanel;
use KraftySprouts\Pollen\Jobs\Handler;
use KraftySprouts\Pollen\Networks\OAuthNetwork;
use KraftySprouts\Pollen\Networks\ShareFailed;
use KraftySprouts\Pollen\Networks\TokenNetwork;
use KraftySprouts\Pollen\Sharing\Alerts;
use KraftySprouts\Pollen\Sharing\Captions;
use KraftySprouts\Pollen\Sharing\Dispatcher;
use KraftySprouts\Pollen\Sharing\Planner;
use KraftySprouts\Pollen\Sharing\Sender;
use KraftySprouts\Pollen\Status\ChannelsNeedYou;
use KraftySprouts\Pollen\Store\Shares;
use KraftySprouts\Pollen\Store\VaultLocked;
use Teme\Contracts\Admin\HasPluginArea;
use Teme\Contracts\Admin\PluginArea;
use Teme\Contracts\Ai\AiRefused;
use Teme\Contracts\Ai\SiteAi;
use Teme\Contracts\Content\ContentDeleted;
use Teme\Contracts\Content\ContentPublished;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ContentTrashed;
use Teme\Contracts\Content\ContentUnpublished;
use Teme\Contracts\Content\ReadRefused;
use Teme\Contracts\Editor\HasEditorPanels;
use Teme\Contracts\Hooks\Hooks;
use Teme\Contracts\Hooks\HookSender;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\Jobs;
use Teme\Contracts\Jobs\PluginHasJobs;
use Teme\Contracts\Notices\Notices;
use Teme\Contracts\People\PeopleReader;
use Teme\Contracts\Plugins\Plugin;
use Teme\Contracts\Settings\SecretReader;
use Teme\Contracts\Settings\SettingsReader;
use Teme\Contracts\Site\SiteInfo;
use Teme\Contracts\Status\ChecksStatus;
use Throwable;

/**
 * Pollen's plugin class: built by Teme with the doors Pollen's capabilities
 * open, it listens for items being published and leaving the site, answers
 * the handlers of its jobs, and hands Teme its area, its editor panel and
 * its status check.
 *
 * @since 1.0.0
 */
final class Pollen implements ChecksStatus, HasEditorPanels, HasPluginArea, Plugin, PluginHasJobs
{
    private readonly Doors $doors;

    /**
     * @since 1.0.0
     */
    public function __construct(
        SettingsReader $settings,
        SecretReader $secrets,
        SiteInfo $site,
        OutboundHttp $outbound,
        Jobs $jobs,
        ContentReader $content,
        PeopleReader $people,
        SiteAi $ai,
        Notices $notices,
        HookSender $hooks,
    ) {
        $this->doors = new Doors(new Settings($settings, $secrets), $site, $outbound, $jobs, $content, $people, $ai, $notices, $hooks);
    }

    public function register(Hooks $hooks): void
    {
        $hooks->on(ContentPublished::class, function (ContentPublished $published): void {
            $item = $published->item;
            $this->doors->items->published($item->id, $item->type, $item->title, (string) $item->url, $published->publishedAt, $published->first);
        });

        $gone = fn (string $itemId): mixed => [$this->doors->items->gone($itemId), $this->doors->shares->cancelItem($itemId, __('The item left the site.'))];

        $hooks->on(ContentUnpublished::class, fn (ContentUnpublished $event): mixed => $gone($event->item->id));
        $hooks->on(ContentTrashed::class, fn (ContentTrashed $event): mixed => $gone($event->item->id));
        $hooks->on(ContentDeleted::class, fn (ContentDeleted $event): mixed => $gone($event->id));
    }

    public function jobs(): array
    {
        return [
            'dispatch' => new Handler(fn (JobContext $c) => (new Dispatcher($this->doors))->run($c)),
            'send-share' => new Handler(fn (JobContext $c) => (new Sender($this->doors))->send((string) ($c->payload()['share'] ?? ''), $c->personId(), $c->attempt(), 3)),
            'tidy' => new Handler(fn (JobContext $c) => $this->tidy($c)),
            'check-channels' => new Handler(fn (JobContext $c) => $this->checkChannels($c)),
            'panel-share' => new Handler(fn (JobContext $c) => $this->panelShare($c)),
            'panel-captions' => new Handler(fn (JobContext $c) => $this->panelCaptions($c)),
            'panel-switch' => new Handler(fn (JobContext $c) => $this->panelSwitch($c)),
        ];
    }

    public function pluginArea(): PluginArea
    {
        return new PollenArea($this->doors);
    }

    public function editorPanels(): array
    {
        return ['share' => new SharePanel($this->doors)];
    }

    public function statusChecks(): array
    {
        return [new ChannelsNeedYou($this->doors)];
    }

    /**
     * Returns the doors, for Pollen's tests.
     *
     * @since 1.0.0
     */
    public function doors(): Doors
    {
        return $this->doors;
    }

    /**
     * Renews sign-ins that expire within a week, forgets old sign-in attempts and image copies, and gives up on shares lost while sending.
     */
    private function tidy(JobContext $context): void
    {
        $context->progress(0, 2, __('Renewing sign-ins'));

        foreach ($this->doors->channels->expiringWithin(7 * 86400) as $channel) {
            $network = $this->doors->networks->find($channel->network);

            if (! $network instanceof OAuthNetwork) {
                continue;
            }

            try {
                $vault = $this->doors->settings->vault();
                $renewed = $network->refresh($this->doors->settings->app($network->app()), $channel, $vault->open($channel->sealed));

                if ($renewed !== null) {
                    $this->doors->channels->connected($channel, $renewed, $vault);
                }
            } catch (ShareFailed|VaultLocked $failed) {
                if ($this->doors->channels->needsReconnect($channel, $failed->getMessage())) {
                    (new Alerts($this->doors))->reconnect($channel, $failed->getMessage());
                }
            }
        }

        $context->progress(1, 2, __('Tidying up'));
        $this->doors->connects->tidy();
        $this->doors->images->tidy();
        \Illuminate\Support\Facades\DB::table('pollen_shares')->where('status', Shares::SENDING)->where('updated_at', '<', now()->subHours(2))
            ->update(['status' => Shares::FAILED, 'reply' => __('Pollen lost track of this share while sending it. Duplicate it to send it again.'), 'updated_at' => now()]);
        \Illuminate\Support\Facades\DB::table('pollen_requests')->where('created_at', '<', now()->subDays(90))->delete();
        $context->progress(2, 2);
    }

    /**
     * Asks each network whether its channels' sign-ins still work, connecting token channels as it goes.
     */
    private function checkChannels(JobContext $context): void
    {
        $only = $context->payload()['channel'] ?? null;
        $channels = is_string($only) ? array_values(array_filter([$this->doors->channels->find($only)])) : $this->doors->channels->all();

        foreach ($channels as $i => $channel) {
            $context->progress($i, count($channels), $channel->name);
            $network = $this->doors->networks->find($channel->network);

            if ($network === null || $channel->sealed === '') {
                continue;
            }

            try {
                $vault = $this->doors->settings->vault();
                $credentials = $vault->open($channel->sealed);

                if ($network instanceof TokenNetwork) {
                    $token = (string) ($credentials['token'] ?? $credentials['password'] ?? '');
                    $this->doors->channels->connected($channel, $network->connect($channel, $token), $vault);
                    $this->doors->shares->release($channel->id);
                } elseif ($network instanceof OAuthNetwork) {
                    $renewed = $network->refresh($this->doors->settings->app($network->app()), $channel, $credentials);

                    if ($renewed !== null) {
                        $this->doors->channels->connected($channel, $renewed, $vault);
                    }
                }
            } catch (ShareFailed|VaultLocked $failed) {
                if ($failed instanceof VaultLocked || $failed->kind === ShareFailed::RECONNECT) {
                    $this->doors->channels->needsReconnect($channel, $failed->getMessage());
                }
            }
        }

        $context->progress(count($channels), count($channels), __('Checked every channel.'));
    }

    /**
     * Plans the writer's shares from the editor's panel.
     */
    private function panelShare(JobContext $context): void
    {
        $person = $context->personId();
        [$itemId, $values] = self::panelPayload($context);
        $facts = $this->facts($itemId, $person);
        $zone = new DateTimeZone($this->doors->people->zone($person));
        $when = trim((string) ($values['when'] ?? ''));
        $published = $facts->status === 'published' && $facts->url !== '';

        try {
            $at = $when === '' ? now()->toDateTimeImmutable() : new DateTimeImmutable($when, $zone);
        } catch (Throwable) {
            throw new \InvalidArgumentException(__('Pollen couldn’t read “:when” as a date and time. Write it as 2026-12-01 09:30.', ['when' => $when]));
        }

        $unknown = [];
        $refused = [];
        $planner = new Planner($this->doors);
        $ids = $planner->byPerson($person, $facts, (string) ($values['channels'] ?? ''), (string) ($values['message'] ?? ''), $at, trim((string) ($values['image'] ?? '')), '', 'panel', $unknown, $refused, $published || $when !== '' ? Shares::PLANNED : Shares::HELD);

        if (! $published && $when === '') {
            foreach ($ids as $id) {
                $this->doors->shares->stopped($id, Shares::HELD, 'awaiting-publication');
            }
        }

        if ($published && $when === '') {
            (new Dispatcher($this->doors))->queueDue();
        }

        $words = [trans_choice('{0} No share was planned.|{1} Planned one share.|[2,*] Planned :count shares.', count($ids), ['count' => count($ids)])];

        if ($unknown !== []) {
            $words[] = __('No channel is named :names.', ['names' => implode(', ', $unknown)]);
        }

        if ($refused !== []) {
            $words[] = __('You may not post to :names.', ['names' => implode(', ', $refused)]);
        }

        $context->progress(1, 1, implode(' ', $words));
    }

    /**
     * Asks the site's AI for captions and leaves them as suggested shares for the writer to choose.
     */
    private function panelCaptions(JobContext $context): void
    {
        $person = $context->personId();
        [$itemId, $values] = self::panelPayload($context);
        $facts = $this->facts($itemId, $person);
        $planner = new Planner($this->doors);
        $unknown = [];
        $refused = [];
        $channels = array_values(array_filter($this->doors->channels->named((string) ($values['channels'] ?? ''), $unknown), fn (Channel $c): bool => $c->active));

        try {
            $captions = (new Captions($this->doors))->many($facts, array_values(array_unique(array_map(fn (Channel $c): string => $c->network, $channels))), (string) ($values['brief'] ?? ''), $person);
        } catch (AiRefused $refusal) {
            $context->progress(1, 1, $refusal->getMessage());

            return;
        }

        $made = 0;

        foreach ($channels as $channel) {
            $text = $captions[$channel->network] ?? $captions[str_replace('-page', '', $channel->network)] ?? null;

            if ($text !== null) {
                $made += count($planner->byPerson($person, $facts, $channel->id, $text, now()->toDateTimeImmutable(), '', '', 'ai', $unknown, $refused, Shares::SUGGESTED));
            }
        }

        $context->progress(1, 1, trans_choice('{0} No caption came back.|{1} One caption waits in Pollen’s Planner for you to choose.|[2,*] :count captions wait in Pollen’s Planner for you to choose.', $made, ['count' => $made]));
    }

    /**
     * Turns sharing off or on for the item.
     */
    private function panelSwitch(JobContext $context): void
    {
        [$itemId, $values] = self::panelPayload($context);
        $this->facts($itemId, $context->personId());
        $off = ($values['sharing'] ?? 'on') === 'off';
        $this->doors->items->optOut($itemId, $off);

        if ($off) {
            $this->doors->shares->cancelItem($itemId, __('Sharing was turned off for this item.'));
        }

        $context->progress(1, 1, $off ? __('This item won’t be shared.') : __('This item is shared as usual.'));
    }

    /**
     * Returns the item, read for the person, or throws one sentence when they may not.
     */
    private function facts(string $itemId, string $person): ItemFacts
    {
        try {
            return ItemFacts::read($this->doors->content ?? throw new \LogicException('No content door.'), $this->doors->peopleReader, $itemId, $person);
        } catch (ReadRefused $refused) {
            throw new \InvalidArgumentException($refused->getMessage());
        }
    }

    /**
     * Returns the item's ID and the form's values from a panel action's payload.
     *
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @since 1.0.0
     */
    public static function panelPayload(JobContext $context): array
    {
        $payload = $context->payload();
        $itemId = (string) ($payload['itemId'] ?? $payload['item_id'] ?? $payload['item'] ?? '');
        $values = $payload['values'] ?? $payload['fields'] ?? $payload['form'] ?? $payload;

        if ($itemId === '') {
            throw new \InvalidArgumentException(__('The panel didn’t say which item this is for.'));
        }

        return [$itemId, is_array($values) ? $values : []];
    }
}
