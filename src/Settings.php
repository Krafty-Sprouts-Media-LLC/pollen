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
use KraftySprouts\Pollen\Store\Vault;
use Teme\Contracts\Settings\SecretReader;
use Teme\Contracts\Settings\SettingsReader;

/**
 * Pollen's settings group, read each time it is needed so a saved change
 * counts at once: the share text, the link tags, the pause switch, and the
 * vault the lock key opens.
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
     * Returns the vault, opened by the lock key.
     *
     * @since 1.0.0
     */
    public function vault(): Vault
    {
        return new Vault((string) $this->secrets->secret(self::GROUP, 'lock_key'));
    }

    /**
     * Returns the share text and the most hashtags for a network.
     *
     * @return array{text: string, first_comment: string, hashtags: int}
     *
     * @since 1.0.0
     */
    public function networkText(string $network): array
    {
        $text = trim($this->string('template')) !== '' ? $this->string('template') : Template::DEFAULT;
        $rows = $this->settings->get(self::GROUP, 'templates');

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && ($row['network'] ?? null) === $network && is_string($row['text'] ?? null) && trim($row['text']) !== '') {
                $text = $row['text'];
            }
        }

        return ['text' => $text, 'first_comment' => '', 'hashtags' => $this->int('hashtags')];
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
}
