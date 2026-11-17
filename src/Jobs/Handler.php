<?php

/**
 * Pollen: a job handler made from a function.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Jobs;

use Closure;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\JobHandler;

/**
 * Runs one of Pollen's declared jobs by calling the function it was made
 * with, so each job's work stays beside the rest of Pollen's.
 *
 * @since 1.0.0
 */
final readonly class Handler implements JobHandler
{
    /**
     * @param  Closure(JobContext): void  $work
     *
     * @since 1.0.0
     */
    public function __construct(private Closure $work) {}

    public function handle(JobContext $context): void
    {
        ($this->work)($context);
    }
}
