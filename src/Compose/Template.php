<?php

/**
 * Pollen: share templates and their variables.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

/**
 * Fills a share template's {variables}. A variable with no value leaves
 * nothing behind, and the blank lines and spaces it would leave are tidied.
 *
 * @since 1.0.0
 */
final class Template
{
    /** The variables a template may use, for the help text and the checks. */
    public const VARIABLES = ['title', 'excerpt', 'content', 'link', 'author', 'hashtags', 'categories', 'tags', 'site', 'type', 'custom'];

    /** The template used when none is set. */
    public const DEFAULT = "{title}\n\n{excerpt}\n\n{link}\n\n{hashtags}";

    /**
     * Returns the template with every {variable} replaced by its value, or by nothing.
     *
     * @param  array<string, string>  $values
     *
     * @since 1.0.0
     */
    public static function render(string $template, array $values): string
    {
        $text = (string) preg_replace_callback('/\{([a-z_]+)\}/', fn (array $m): string => $values[$m[1]] ?? '', $template);
        $text = (string) preg_replace('/[ \t]+$/m', '', $text);
        $text = (string) preg_replace('/^[ \t]+/m', '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Returns the {variables} a template names that Pollen doesn't know.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public static function unknown(string $template): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $template, $m);

        return array_values(array_unique(array_diff($m[1], self::VARIABLES)));
    }
}
