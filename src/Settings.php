<?php

/**
 * Pollen: reading Pollen's settings and secrets.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen;

use KraftySprouts\Pollen\Compose\Template;
use KraftySprouts\Pollen\Compose\Utm;
use KraftySprouts\Pollen\Networks\AppKeys;
use KraftySprouts\Pollen\Store\Vault;
use Teme\Contracts\Settings\SecretReader;
use Teme\Contracts\Settings\SettingsReader;

/**
 * Pollen's settings group, read each time it is needed so a saved change
 * counts at once: the templates, the link tags, the image choices, the app
 * keys of each network, and the vault the lock key opens.
 *
 * @since 1.0.0
 */
final readonly class Settings
{
    /** Pollen's settings group. */
    public const GROUP = 'plugin.pollen';

    /**
     * @since 1.0.0
     */
    public function __construct(
        private SettingsReader $settings,
        private SecretReader $secrets,
    ) {}

    /**
     * Returns a text setting.
     *
     * @since 1.0.0
     */
    public function string(string $key): string
    {
        return $this->settings->string(self::GROUP, $key);
    }

    /**
     * Returns a number setting.
     *
     * @since 1.0.0
     */
    public function int(string $key): int
    {
        return $this->settings->int(self::GROUP, $key);
    }

    /**
     * Returns a yes-or-no setting.
     *
     * @since 1.0.0
     */
    public function bool(string $key): bool
    {
        return $this->settings->bool(self::GROUP, $key);
    }

    /**
     * Returns a list setting, such as the servers.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function list(string $key): array
    {
        return array_values($this->settings->stringList(self::GROUP, $key));
    }

    /**
     * Returns the vault, opened by the lock key.
     *
     * @since 1.0.0
     */
    public function vault(): Vault
    {
        return new Vault((string) $this->secrets->secret(self::GROUP, 'lock_key'));
    }

    /**
     * Returns the keys of the site owner's app for a network's sign-in.
     *
     * @since 1.0.0
     */
    public function app(string $prefix): AppKeys
    {
        $idKey = match ($prefix) {
            'plurk' => 'plurk_app_key',
            'facebook', 'threads', 'pinterest' => $prefix.'_app_id',
            default => $prefix.'_client_id',
        };
        $secretKey = match ($prefix) {
            'plurk', 'facebook', 'threads', 'pinterest' => $prefix.'_app_secret',
            default => $prefix.'_client_secret',
        };

        return new AppKeys(trim($this->string($idKey)), trim((string) $this->secrets->secret(self::GROUP, $secretKey)));
    }

    /**
     * Returns the template and first comment for a network, with the most hashtags.
     *
     * @return array{text: string, first_comment: string, hashtags: int}
     *
     * @since 1.0.0
     */
    public function networkText(string $network): array
    {
        $base = $this->string('template');
        $text = ['text' => $base !== '' ? $base : Template::DEFAULT, 'first_comment' => '', 'hashtags' => $this->int('hashtags')];
        $family = $network === 'linkedin-page' ? 'linkedin' : $network;
        $repeater = $this->settings->get(self::GROUP, 'templates');

        foreach (is_array($repeater) ? $repeater : [] as $row) {
            if (! is_array($row) || ($row['network'] ?? null) !== $family) {
                continue;
            }

            if (is_string($row['text'] ?? null) && trim($row['text']) !== '') {
                $text['text'] = $row['text'];
            }

            $text['first_comment'] = is_string($row['first_comment'] ?? null) ? $row['first_comment'] : '';

            if (is_numeric($row['hashtags'] ?? null)) {
                $text['hashtags'] = (int) $row['hashtags'];
            }
        }

        return $text;
    }

    /**
     * Returns the link tags to add, or null when tagging is off.
     *
     * @since 1.0.0
     */
    public function utm(): ?Utm
    {
        return $this->bool('utm') ? new Utm($this->string('utm_source'), $this->string('utm_medium'), $this->string('utm_campaign')) : null;
    }

    /**
     * Returns the hosts Pollen may fetch images from: the image host and the watermark's.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function imageHosts(): array
    {
        $hosts = [];

        foreach (['image_host', 'watermark_image'] as $key) {
            $host = parse_url($this->string($key), PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return $hosts;
    }

    /**
     * Returns whether the server's host is among those listed under Servers.
     *
     * @since 1.0.0
     */
    public function serverListed(string $host): bool
    {
        foreach ($this->list('servers') as $server) {
            if (strtolower((string) parse_url($server, PHP_URL_HOST)) === strtolower($host)) {
                return true;
            }
        }

        return false;
    }
}
