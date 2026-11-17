<?php

/**
 * Pollen: campaign tags added to shared links.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

/**
 * Adds utm_source, utm_medium and utm_campaign to a link, each from a pattern
 * that may name {network}, {channel}, {slug}, {id} and {type}. An empty
 * pattern adds nothing; a link's own query and fragment are kept.
 *
 * @since 1.0.0
 */
final readonly class Utm
{
    /**
     * @since 1.0.0
     */
    public function __construct(
        public string $source,
        public string $medium,
        public string $campaign,
    ) {}

    /**
     * Returns the link with the tags added.
     *
     * @param  array<string, string>  $values
     *
     * @since 1.0.0
     */
    public function apply(string $link, array $values): string
    {
        $tags = [];

        foreach (['utm_source' => $this->source, 'utm_medium' => $this->medium, 'utm_campaign' => $this->campaign] as $name => $pattern) {
            $value = trim(Template::render($pattern, $values));
            $value = (string) preg_replace('/[^\p{L}\p{N}._-]+/u', '-', $value);

            if ($value !== '') {
                $tags[$name] = $value;
            }
        }

        if ($tags === [] || $link === '') {
            return $link;
        }

        $fragment = '';

        if (($hash = strpos($link, '#')) !== false) {
            $fragment = substr($link, $hash);
            $link = substr($link, 0, $hash);
        }

        return $link.(str_contains($link, '?') ? '&' : '?').http_build_query($tags, '', '&', PHP_QUERY_RFC3986).$fragment;
    }
}
