<?php

/**
 * Pollen: the minute's work.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use DateTimeZone;
use KraftySprouts\Pollen\Doors;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\QueueOptions;
use Throwable;

/**
 * Pollen's scheduled pass, each minute, as the administrator who enabled
 * Pollen: plans the shares of items newly published, takes other plugins'
 * requests, runs the re-share automations that are due, and queues a
 * send-share job for each share whose time has come.
 *
 * @since 1.0.0
 */
final readonly class Dispatcher
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Does the minute's work and returns how many shares it queued.
     *
     * @since 1.0.0
     */
    public function run(JobContext $context): int
    {
        $person = $context->personId();
        $planner = new Planner($this->doors);
        $context->progress(0, 4, __('Planning new items'));

        foreach ($this->doors->items->takeWaiting() as $item) {
            $planner->published($item, $person);
        }

        $context->progress(1, 4, __('Taking requests from other plugins'));
        $planner->requests();

        $context->progress(2, 4, __('Re-sharing older items'));
        $resharer = new Resharer($this->doors);

        foreach ($this->doors->automations->due() as $automation) {
            try {
                // Teme tells a plugin the site's time zone only in its area, so each automation keeps the one it was saved in.
                $zone = new DateTimeZone(is_string($automation->settings['_zone'] ?? null) && $automation->settings['_zone'] !== '' ? $automation->settings['_zone'] : 'UTC');
                $resharer->run($automation, $person, $zone);
            } catch (Throwable $failed) {
                $this->doors->automations->ran($automation->id, now()->addHour(), $automation->turn, mb_substr($failed->getMessage(), 0, 200));
            }
        }

        $context->progress(3, 4, __('Sending what is due'));

        return $this->queueDue();
    }

    /**
     * Queues a send-share job for each share whose time has come, once while one waits; returns how many.
     *
     * @since 1.0.0
     */
    public function queueDue(): int
    {
        if ($this->doors->jobs === null || $this->doors->settings->bool('pause')) {
            return 0;
        }

        $queued = 0;

        foreach ($this->doors->shares->due() as $shareId) {
            try {
                $this->doors->jobs->queue('send-share', ['share' => $shareId], null, new QueueOptions(uniqueKey: 'share-'.$shareId));
            } catch (Throwable) {
                // On a queue that runs jobs at once, a send's own failure arrives here; the share records it.
            }

            $queued++;
        }

        return $queued;
    }
}
