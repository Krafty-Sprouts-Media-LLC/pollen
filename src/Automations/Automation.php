<?php

/**
 * Pollen: one automation.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Automations;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A rule that shares by itself: when a matching item is published, or again
 * and again from older items. It says which items match, which channels the
 * shares go to and with what text, and for re-sharing, how often, on which
 * days and hours, in what order and how soon an item may come round again.
 *
 * @since 1.0.0
 */
final readonly class Automation
{
    /** Shares a matching item when it is published. */
    public const PUBLISH = 'publish';

    /** Re-shares older items on a timetable. */
    public const RESHARE = 'reshare';

    /**
     * @param  array<string, mixed>  $settings  the form's values
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $kind,
        public bool $active,
        public array $settings,
        public ?int $nextRunAt,
        public int $turn,
        public string $createdBy,
    ) {}

    /**
     * Returns an automation from its row.
     *
     * @param  array<string, mixed>  $row
     *
     * @since 1.0.0
     */
    public static function fromRow(array $row): self
    {
        $settings = json_decode((string) ($row['settings'] ?? '{}'), true);

        return new self(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['kind'],
            (bool) $row['active'],
            is_array($settings) ? $settings : [],
            ($row['next_run_at'] ?? null) === null ? null : (int) strtotime((string) $row['next_run_at']),
            (int) ($row['turn'] ?? 0),
            (string) ($row['created_by'] ?? ''),
        );
    }

    /**
     * Returns a text setting.
     *
     * @since 1.0.0
     */
    public function text(string $key): string
    {
        $value = $this->settings[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Returns a number setting.
     *
     * @since 1.0.0
     */
    public function number(string $key, int $default = 0): int
    {
        $value = $this->settings[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Returns a list of words from a comma-separated setting, in lower case.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function words(string $key): array
    {
        return array_values(array_filter(array_map(fn (string $w): string => mb_strtolower(trim($w)), preg_split('/[,\n]+/', $this->text($key)) ?: []), fn (string $w): bool => $w !== ''));
    }

    /**
     * Returns the content types it covers: posts when none is chosen.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function types(): array
    {
        $types = $this->settings['types'] ?? [];
        $types = is_array($types) ? array_values(array_filter(array_map('strval', $types))) : [];

        return $types === [] ? ['post'] : $types;
    }

    /**
     * Returns the items named under Never these items: IDs, and the last part of addresses.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function excludedItems(): array
    {
        return $this->words('exclude_items');
    }

    /**
     * Returns whether an item of this type, filed under these terms, matches the automation.
     *
     * @param  array<string, list<string>>  $terms  term slugs by taxonomy
     *
     * @since 1.0.0
     */
    public function matches(string $itemId, string $type, string $url, array $terms): bool
    {
        if (! in_array($type, $this->types(), true)) {
            return false;
        }

        foreach ($this->excludedItems() as $excluded) {
            if ($excluded === $itemId || ($url !== '' && rtrim($excluded, '/') === rtrim(mb_strtolower($url), '/'))) {
                return false;
            }
        }

        $all = array_map('mb_strtolower', array_merge(...array_values(array_map(fn (array $s): array => array_values($s), $terms)) ?: [[]]));
        $categories = array_map('mb_strtolower', $terms['category'] ?? $terms['categories'] ?? []);
        $tags = array_map('mb_strtolower', $terms['tag'] ?? $terms['post_tag'] ?? $terms['tags'] ?? []);

        if (array_intersect($this->words('exclude_terms'), $all) !== []) {
            return false;
        }

        if ($this->words('categories') !== [] && array_intersect($this->words('categories'), $categories) === []) {
            return false;
        }

        return $this->words('tags') === [] || array_intersect($this->words('tags'), $tags) !== [];
    }

    /**
     * Returns the next moment a re-share may run after $from: $every minutes on, moved to the next allowed day and hour.
     *
     * @since 1.0.0
     */
    public function nextRun(DateTimeImmutable $from, DateTimeZone $zone): DateTimeImmutable
    {
        $every = max(30, $this->number('every', 240));

        return $this->allowed($from->setTimezone($zone)->modify('+'.$every.' minutes'));
    }

    /**
     * Returns the first allowed moment at or after $at, within the days and hours set.
     *
     * @since 1.0.0
     */
    public function allowed(DateTimeImmutable $at): DateTimeImmutable
    {
        $days = $this->settings['days'] ?? [];
        $days = is_array($days) ? array_map('intval', $days) : [];
        $from = min(23, max(0, $this->number('from_hour', 0)));
        $to = min(23, max(0, $this->number('to_hour', 23)));

        for ($i = 0; $i < 24 * 8; $i++) {
            $hour = (int) $at->format('G');
            $dayOk = $days === [] || in_array((int) $at->format('N'), $days, true);
            $hourOk = $from <= $to ? ($hour >= $from && $hour <= $to) : ($hour >= $from || $hour <= $to);

            if ($dayOk && $hourOk) {
                return $at;
            }

            $next = $at->modify('+1 hour');
            $at = $next->setTime((int) $next->format('G'), 0);
        }

        return $at;
    }
}
