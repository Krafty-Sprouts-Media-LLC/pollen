<?php

/**
 * Pollen: fitting share text to a network's length limit.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

/**
 * Shortens share text to a network's limit without breaking its links:
 * the words around them are cut at a word, and every link is
 * counted as the network counts it (X counts each as 23 characters).
 *
 * @since 1.0.0
 */
final class Fitter
{
    private const LINK = '#https?://\S+#u';

    /**
     * Returns the text's length as the network counts it.
     *
     * @since 1.0.0
     */
    public static function length(string $text, ?int $linkWeight = null): int
    {
        if ($linkWeight === null) {
            return mb_strlen($text);
        }

        $links = preg_match_all(self::LINK, $text, $m);
        $bare = (string) preg_replace(self::LINK, '', $text);

        return mb_strlen($bare) + $links * $linkWeight;
    }

    /**
     * Returns the text cut to $max as the network counts it, keeping its links whole.
     *
     * @since 1.0.0
     */
    public static function fit(string $text, int $max, ?int $linkWeight = null): string
    {
        $text = trim($text);

        if ($max <= 0 || self::length($text, $linkWeight) <= $max) {
            return $text;
        }

        // Keep the links, which carry the share, and cut the words around them.
        preg_match_all(self::LINK, $text, $links);
        $prose = trim((string) preg_replace(self::LINK, '', $text));
        $kept = implode(' ', $links[0]);
        $room = $max - ($kept === '' ? 0 : 1 + self::length($kept, $linkWeight));

        if ($room <= 1) {
            return self::hardCut($kept !== '' ? $kept : $text, $max);
        }

        $cut = TiptapText::cut((string) preg_replace('/[ \t]*\n[ \t]*/', "\n", $prose), $room);

        return trim($cut.($kept === '' ? '' : "\n".$kept));
    }

    /**
     * Returns text cut hard at $max characters.
     */
    private static function hardCut(string $text, int $max): string
    {
        return mb_strlen($text) <= $max ? $text : mb_substr($text, 0, max(0, $max - 1)).'…';
    }
}
