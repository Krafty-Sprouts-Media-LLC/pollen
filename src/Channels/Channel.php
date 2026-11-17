<?php

/**
 * Pollen: one connected account or page.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Channels;

/**
 * A channel as Pollen keeps it: one place on one network that shares go to,
 * such as a Facebook Page or a Telegram channel, with its labels, who may
 * post there, and whether its sign-in works. Its tokens stay sealed apart.
 *
 * @since 1.0.0
 */
final readonly class Channel
{
    /** Saved, but never connected. */
    public const NEW = 'new';

    /** Signed in and ready. */
    public const CONNECTED = 'connected';

    /** Signed in, but the account has several pages or boards and none was named. */
    public const CHOOSE = 'choose';

    /** The network no longer accepts the sign-in. */
    public const RECONNECT = 'reconnect';

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $autoTypes  content types whose new items are shared here
     * @param  array<string, mixed>  $options  the channel's writing choices and network extras
     * @param  list<string>  $people  account IDs allowed when $access is listed
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $network,
        public string $account,
        public string $accountName,
        public array $labels,
        public array $autoTypes,
        public string $server,
        public string $handle,
        public string $sealed,
        public ?int $tokenExpiresAt,
        public array $options,
        public string $status,
        public bool $active,
        public int $failures,
        public string $lastError,
        public string $access,
        public array $people,
        public string $createdBy,
    ) {}

    /**
     * Returns one of the channel's writing choices, or the default.
     *
     * @since 1.0.0
     */
    public function option(string $key, string $default = ''): string
    {
        $value = $this->options[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    /**
     * Returns whether shares can go here now.
     *
     * @since 1.0.0
     */
    public function ready(): bool
    {
        return $this->active && $this->status === self::CONNECTED;
    }

    /**
     * Returns the server's host, for networks on a server the site chose.
     *
     * @since 1.0.0
     */
    public function serverHost(string $default = ''): string
    {
        $host = parse_url($this->server, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : $default;
    }
}
