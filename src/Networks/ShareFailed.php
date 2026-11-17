<?php

/**
 * Pollen: a network's refusal, sorted.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use RuntimeException;

/**
 * Thrown when a network didn't take a post, sorted by what Pollen does next:
 * try again later, give up on this share, or mark the channel as needing to
 * be connected again. The message is a short sentence for the log.
 *
 * @since 1.0.0
 */
final class ShareFailed extends RuntimeException
{
    /** Try again later: the network was busy, down, or said to wait. */
    public const RETRY = 'retry';

    /** Don't try again: the network refused this post. */
    public const FINAL = 'final';

    /** The channel's sign-in no longer works. */
    public const RECONNECT = 'reconnect';

    /**
     * @since 1.0.0
     */
    public function __construct(public readonly string $kind, string $message, public readonly int $waitSeconds = 0)
    {
        parent::__construct(mb_substr($message, 0, 480));
    }

    /**
     * Returns a failure worth trying again.
     *
     * @since 1.0.0
     */
    public static function retry(string $message, int $waitSeconds = 0): self
    {
        return new self(self::RETRY, $message, $waitSeconds);
    }

    /**
     * Returns a failure not worth trying again.
     *
     * @since 1.0.0
     */
    public static function final(string $message): self
    {
        return new self(self::FINAL, $message);
    }

    /**
     * Returns a failure that needs the channel connected again.
     *
     * @since 1.0.0
     */
    public static function reconnect(string $message): self
    {
        return new self(self::RECONNECT, $message);
    }
}
