<?php

/**
 * Pollen: bringing FS Poster's and Revive Social's settings across from WordPress.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Import;

use KraftySprouts\Pollen\Store\Automations;
use Teme\Contracts\Import\ImportOutcome;
use Teme\Contracts\Import\ImportScope;
use Teme\Contracts\Import\WordPressImporter;
use Teme\Contracts\Import\WordPressRecord;

/**
 * Reads the WordPress options FS Poster and Revive Social keep their
 * settings in, and turns them into Pollen automations, switched off for the
 * site's owner to check: Revive's re-share plan with its filters and text,
 * and FS Poster's auto-share and each network's template. Tokens never come
 * across; channels are connected again. Each automation's note says what
 * didn't come across.
 *
 * @since 1.0.0
 */
final readonly class WordPressImport implements WordPressImporter
{
    /** The networks whose FS Poster template options Pollen reads, by FS Poster's prefix, as Pollen's network key. */
    public const FS_NETWORKS = [
        'fb' => 'facebook', 'instagram' => 'instagram', 'twitter' => 'x', 'linkedin' => 'linkedin', 'pinterest' => 'pinterest',
        'telegram' => 'telegram', 'bluesky' => 'bluesky', 'threads' => 'threads', 'mastodon' => 'mastodon', 'reddit' => 'reddit',
        'discord' => 'discord', 'google_b' => 'google-business', 'tumblr' => 'tumblr', 'vk' => 'vk', 'plurk' => 'plurk',
        'blogger' => 'blogger', 'wordpress' => 'wordpress',
    ];

    /** The intervals an automation offers, in minutes. */
    private const EVERY = [30, 60, 120, 180, 240, 360, 480, 720, 1440, 2880, 10080];

    public function options(array $options, ImportScope $scope): void
    {
        $automations = new Automations;
        $this->revive($options, $scope, $automations);
        $this->fsPoster($options, $automations);
    }

    public function record(WordPressRecord $record, ImportScope $scope): ImportOutcome
    {
        return ImportOutcome::skipped(__('Pollen reads only options from WordPress.'));
    }

    /**
     * Makes a re-share automation from Revive Social's settings.
     *
     * @param  array<string, mixed>  $options
     */
    private function revive(array $options, ImportScope $scope, Automations $automations): void
    {
        $data = is_array($options['rop_data'] ?? null) ? $options['rop_data'] : null;
        $general = is_array($data['general_settings'] ?? null) ? $data['general_settings'] : null;

        if ($general === null) {
            $general = self::legacyRevive($options);

            if ($general === null) {
                return;
            }
        }

        $types = [];

        foreach (is_array($general['selected_post_types'] ?? null) ? $general['selected_post_types'] : [] as $type) {
            $value = is_array($type) ? (string) ($type['value'] ?? '') : (string) $type;
            $types[] = $value === 'post' || $value === 'page' ? $value : null;
        }

        $terms = [];

        foreach (is_array($general['selected_taxonomies'] ?? null) ? $general['selected_taxonomies'] : [] as $term) {
            if (is_array($term) && ($term['value'] ?? '') !== 'all' && is_string($term['name'] ?? null)) {
                $terms[] = self::slug($term['name']);
            }
        }

        $excluded = [];
        $missing = 0;

        foreach (is_array($general['selected_posts'] ?? null) ? $general['selected_posts'] : [] as $post) {
            $item = $scope->itemFor((int) (is_array($post) ? ($post['value'] ?? 0) : $post));
            $item === null ? $missing++ : $excluded[] = $item->id;
        }

        $format = is_array($data['post_format'] ?? null) ? (array_values($data['post_format'])[0] ?? []) : [];
        $hours = (float) ($general['default_interval'] ?? 12);
        $schedule = is_array($options['rop_schedules_data']['schedules'] ?? null) ? array_values($options['rop_schedules_data']['schedules'])[0] ?? [] : [];

        if (is_array($schedule) && ($schedule['type'] ?? '') === 'recurring' && is_numeric($schedule['interval_r'] ?? null)) {
            $hours = (float) $schedule['interval_r'];
        }

        $exclude = (bool) ($general['exclude_taxonomies'] ?? false);
        $settings = [
            'channels' => 'imported',
            'template' => is_array($format) ? self::reviveTemplate($format) : '',
            'ai' => false,
            'types' => array_values(array_filter($types)) ?: ['post'],
            'categories' => $exclude ? '' : implode(', ', $terms),
            'tags' => '',
            'exclude_terms' => $exclude ? implode(', ', $terms) : '',
            'exclude_items' => (bool) ($general['exclude_posts'] ?? true) ? implode("\n", $excluded) : '',
            'older_than' => (int) ($general['minimum_post_age'] ?? 30),
            'newer_than' => (int) ($general['maximum_post_age'] ?? 0),
            'every' => (string) self::nearest((int) round($hours * 60)),
            'days' => [],
            'from_hour' => 0,
            'to_hour' => 23,
            'per_run' => max(1, min(4, (int) ($general['number_of_posts'] ?? 1))),
            'order' => 'random',
            'repeat_after' => 0,
            'rotate' => false,
        ];

        if (is_array($schedule) && ($schedule['type'] ?? '') === 'fixed' && is_array($schedule['interval_f'] ?? null)) {
            $settings['days'] = array_values(array_map('strval', is_array($schedule['interval_f']['week_days'] ?? null) ? $schedule['interval_f']['week_days'] : []));
            $settings['every'] = '1440';
        }

        $id = $automations->save(null, __('Re-share (from Revive Social)'), 'reshare', $settings, '');
        $automations->setActive([$id], false);

        $notes = [__('Brought from Revive Social and switched off: label your channels “imported”, check it, and switch it on.')];

        if ($missing > 0) {
            $notes[] = __(':count excluded posts weren’t brought by the import.', ['count' => $missing]);
        }

        $notes[] = __('Not brought: connected accounts and their tokens, per-account schedules and filters, per-post messages, shorteners and the keyword filter.');
        $automations->ran($id, null, 0, implode(' ', $notes));
    }

    /**
     * Makes automations from FS Poster's auto-share settings and each network's template.
     *
     * @param  array<string, mixed>  $options
     */
    private function fsPoster(array $options, Automations $automations): void
    {
        $types = array_values(array_intersect(is_array($options['fsp_allowed_post_types'] ?? null) ? array_map('strval', $options['fsp_allowed_post_types']) : ['post'], ['post', 'page'])) ?: ['post'];
        $delay = (int) ($options['fsp_auto_share_delay'] ?? 0);
        $any = false;

        foreach (self::FS_NETWORKS as $prefix => $network) {
            $template = $options['fsp_'.$prefix.'_post_text'] ?? $options['fsp_'.$prefix.'_post_content'] ?? null;

            if (! is_string($template) || trim($template) === '') {
                continue;
            }

            $any = true;
            $comment = $options['fsp_'.$prefix.'_first_comment_text'] ?? null;
            $settings = [
                'channels' => $network,
                'template' => self::fsTemplate($template),
                'ai' => false,
                'types' => $types,
                'categories' => '',
                'tags' => '',
                'exclude_terms' => '',
                'exclude_items' => '',
                'delay' => max(0, min(10080, intdiv($delay, 60))),
            ];

            $id = $automations->save(null, __(':network on publish (from FS Poster)', ['network' => $network]), 'publish', $settings, '');
            $automations->setActive([$id], false);
            $automations->ran($id, null, 0, implode(' ', array_filter([
                __('Brought from FS Poster and switched off: check it, then switch it on.'),
                is_string($comment) && trim($comment) !== '' ? __('Its first comment was: :text', ['text' => mb_substr(self::fsTemplate($comment), 0, 200)]) : '',
                __('Not brought: channels and their tokens, planners, schedules, labels, permissions, AI templates and watermark rules.'),
            ])));
        }

        if (! $any && isset($options['fsp_auto_share'])) {
            $id = $automations->save(null, __('Share on publish (from FS Poster)'), 'publish', ['channels' => 'imported', 'template' => '', 'types' => $types, 'delay' => max(0, intdiv($delay, 60))], '');
            $automations->setActive([$id], false);
        }
    }

    /**
     * Returns an FS Poster template in Pollen's variables; spun choices keep their first choice.
     *
     * @since 1.0.0
     */
    public static function fsTemplate(string $template): string
    {
        $map = [
            'post_title' => 'title', 'post_excerpt' => 'excerpt', 'post_content' => 'content', 'product_description' => 'content',
            'post_url' => 'link', 'post_short_url' => 'link', 'post_author' => 'author', 'post_author_username' => 'author',
            'hashtags' => 'hashtags', 'hashtags_categories' => 'hashtags', 'hashtags_tags' => 'hashtags',
            'site_name' => 'site', 'social_network_name' => '', 'social_network_slug' => '', 'post_id' => '', 'uniq_id' => '',
            'post_featured_image_url' => '', 'post_slug' => '', 'site_url' => '', 'schedule_id' => '', 'customfield' => '', 'ai_text_generator' => '',
        ];

        $template = (string) preg_replace_callback('/\{([a-z_]+)(?:\s[^{}|]*)?\}/', function (array $m) use ($map): string {
            $name = $map[$m[1]] ?? null;

            return $name === null ? $m[0] : ($name === '' ? '' : '{'.$name.'}');
        }, $template);

        // Spintax {a|b|c}: keep the first choice.
        return trim((string) preg_replace_callback('/\{([^{}]*\|[^{}]*)\}/', fn (array $m): string => explode('|', $m[1])[0], $template));
    }

    /**
     * Returns a Pollen template matching a Revive Social account's post format.
     *
     * @param  array<string, mixed>  $format
     *
     * @since 1.0.0
     */
    public static function reviveTemplate(array $format): string
    {
        $body = match ((string) ($format['post_content'] ?? 'post_title')) {
            'post_content' => '{content}',
            'post_title_content' => "{title}\n\n{content}",
            'post_excerpt' => '{excerpt}',
            'custom_content' => '',
            default => '{title}',
        };

        $custom = trim((string) ($format['custom_text'] ?? ''));

        if ($custom !== '') {
            $body = ($format['custom_text_pos'] ?? 'beginning') === 'end' ? trim($body."\n\n".$custom) : trim($custom."\n\n".$body);
        }

        if ((bool) ($format['include_link'] ?? true)) {
            $body .= "\n\n{link}";
        }

        if (! in_array((string) ($format['hashtags'] ?? 'no-hashtags'), ['no-hashtags', ''], true)) {
            $body .= "\n\n".((string) ($format['hashtags'] ?? '') === 'common-hashtags' ? trim((string) ($format['hashtags_common'] ?? '')) : '{hashtags}');
        }

        return trim($body);
    }

    /**
     * Returns Revive Social's general settings from its older option layout, or null.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    private static function legacyRevive(array $options): ?array
    {
        $old = $options['top_opt_post_formats'] ?? null;

        if (! is_array($old)) {
            return null;
        }

        return [
            'default_interval' => (float) ($old['top_opt_interval'] ?? 12),
            'minimum_post_age' => (int) ($old['top_opt_age_limit'] ?? 30),
            'maximum_post_age' => (int) ($old['top_opt_max_age_limit'] ?? 0),
            'number_of_posts' => (int) ($old['top_opt_no_of_tweet'] ?? 1),
            'selected_post_types' => [['value' => 'post']],
            'selected_posts' => array_map(fn ($id): array => ['value' => $id], is_array($options['top_opt_excluded_post'] ?? null) ? $options['top_opt_excluded_post'] : array_filter(explode(',', (string) ($options['top_opt_excluded_post'] ?? '')))),
            'exclude_posts' => true,
        ];
    }

    /**
     * Returns the nearest interval an automation offers.
     */
    private static function nearest(int $minutes): int
    {
        $best = self::EVERY[0];

        foreach (self::EVERY as $every) {
            if (abs($every - $minutes) < abs($best - $minutes)) {
                $best = $every;
            }
        }

        return $best;
    }

    /**
     * Returns a term name as a slug.
     */
    private static function slug(string $name): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name)), '-');
    }
}
