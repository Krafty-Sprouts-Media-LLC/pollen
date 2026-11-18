<?php

/**
 * Pollen: the plugin Teme starts.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen;

use KraftySprouts\Pollen\Jobs\Handler;
use KraftySprouts\Pollen\Sharing\Dispatcher;
use KraftySprouts\Pollen\Sharing\Sender;
use Teme\Contracts\Content\ContentDeleted;
use Teme\Contracts\Content\ContentPublished;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ContentTrashed;
use Teme\Contracts\Content\ContentUnpublished;
use Teme\Contracts\Hooks\Hooks;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\Jobs;
use Teme\Contracts\Jobs\PluginHasJobs;
use Teme\Contracts\People\PeopleReader;
use Teme\Contracts\Plugins\Plugin;
use Teme\Contracts\Settings\SecretReader;
use Teme\Contracts\Settings\SettingsReader;
use Teme\Contracts\Site\SiteInfo;

/**
 * Pollen's plugin class: built by Teme with the doors Pollen's capabilities
 * open, it records items being published and leaving the site, and answers
 * the handlers of its jobs: the minute's pass and the sending of one share.
 *
 * @since 1.0.0
 */
final class Pollen implements Plugin, PluginHasJobs
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
    ) {
        $this->doors = new Doors(new Settings($settings, $secrets), $site, $outbound, $jobs, $content, $people);
    }

    public function register(Hooks $hooks): void
    {
        $hooks->on(ContentPublished::class, function (ContentPublished $published): void {
            $item = $published->item;
            $this->doors->items->published($item->id, $item->type, $item->title, (string) $item->url, $published->publishedAt, $published->first, $published->actorId);
        });

        $hooks->on(ContentUnpublished::class, fn (ContentUnpublished $event) => $this->gone($event->item->id));
        $hooks->on(ContentTrashed::class, fn (ContentTrashed $event) => $this->gone($event->item->id));
        $hooks->on(ContentDeleted::class, fn (ContentDeleted $event) => $this->gone($event->id));
    }

    public function jobs(): array
    {
        return [
            'dispatch' => new Handler(fn (JobContext $c) => (new Dispatcher($this->doors))->run($c)),
            'send-share' => new Handler(fn (JobContext $c) => (new Sender($this->doors))->send((string) ($c->payload()['share'] ?? ''), $c->personId(), $c->attempt())),
        ];
    }

    /**
     * Forgets an item that left the site, and cancels its shares that haven't gone.
     */
    private function gone(string $itemId): void
    {
        $this->doors->items->gone($itemId);
        $this->doors->shares->cancelItem($itemId, __('The item left the site.'));
    }
}
