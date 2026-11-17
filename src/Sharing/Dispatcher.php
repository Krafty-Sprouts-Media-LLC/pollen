<?php

/**
 * Pollen: the minute's work.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Sharing;

use KraftySprouts\Pollen\Doors;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\QueueOptions;
use Throwable;

/**
 * Pollen's scheduled pass, each minute, as the administrator who enabled
 * Pollen: plans the shares of items newly published, then queues a
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
        $context->progress(0, 2, __('Planning new items'));

        foreach ($this->doors->items->takeWaiting() as $item) {
            $planner->published($item, $person);
        }

        $context->progress(1, 2, __('Sending what is due'));
        $queued = $this->queueDue();
        $context->progress(2, 2, trans_choice('{0} Nothing was due.|{1} Queued one share.|[2,*] Queued :count shares.', $queued, ['count' => $queued]));

        return $queued;
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
