<?php

/**
 * Pollen: plain text and images read out of a Tiptap document.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

/**
 * Reads what a share needs from an item's stored Tiptap document: its words
 * as plain text, a short excerpt cut at a word, and the first image's address.
 *
 * @since 1.0.0
 */
final class TiptapText
{
    /** Block nodes whose text stands as its own paragraph. */
    private const BLOCKS = ['paragraph', 'heading', 'blockquote', 'listItem', 'codeBlock', 'tableCell', 'tableHeader'];

    /**
     * Returns the document's words, one paragraph a block, separated by blank lines.
     *
     * @param  array<string, mixed>  $document
     *
     * @since 1.0.0
     */
    public static function plain(array $document): string
    {
        $blocks = [];
        self::walk($document, $blocks);

        return implode("\n\n", array_values(array_filter(array_map('trim', $blocks), fn (string $b): bool => $b !== '')));
    }

    /**
     * Returns the document's words on one line, cut at a word to at most $max characters with an ellipsis.
     *
     * @param  array<string, mixed>  $document
     *
     * @since 1.0.0
     */
    public static function excerpt(array $document, int $max = 200): string
    {
        return self::cut((string) preg_replace('/\s+/u', ' ', self::plain($document)), $max);
    }

    /**
     * Returns a string cut at a word to at most $max characters, ending with an ellipsis when cut.
     *
     * @since 1.0.0
     */
    public static function cut(string $text, int $max): string
    {
        $text = trim($text);

        if ($max <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > $max / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n,;:.-").'…';
    }

    /**
     * Returns the address of the document's first image with an absolute https or http address, or null.
     *
     * @param  array<string, mixed>  $document
     *
     * @since 1.0.0
     */
    public static function firstImage(array $document): ?string
    {
        $attrs = is_array($document['attrs'] ?? null) ? $document['attrs'] : [];

        if (in_array($document['type'] ?? null, ['image', 'figure', 'media'], true)) {
            foreach (['src', 'url'] as $key) {
                $src = $attrs[$key] ?? null;

                if (is_string($src) && preg_match('#^https?://#i', $src) === 1) {
                    return $src;
                }
            }
        }

        foreach (is_array($document['content'] ?? null) ? $document['content'] : [] as $child) {
            if (is_array($child) && ($found = self::firstImage($child)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Gathers each block's text into $blocks, depth first.
     *
     * @param  array<string, mixed>  $node
     * @param  list<string>  $blocks
     */
    private static function walk(array $node, array &$blocks): void
    {
        $type = $node['type'] ?? null;

        if (in_array($type, self::BLOCKS, true) && ! self::hasBlockChild($node)) {
            $blocks[] = self::inline($node);

            return;
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                self::walk($child, $blocks);
            }
        }
    }

    /**
     * Returns whether a node holds block nodes, as a list item holds paragraphs.
     *
     * @param  array<string, mixed>  $node
     */
    private static function hasBlockChild(array $node): bool
    {
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child) && in_array($child['type'] ?? null, [...self::BLOCKS, 'bulletList', 'orderedList'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the inline text inside one block.
     *
     * @param  array<string, mixed>  $node
     */
    private static function inline(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return is_string($node['text'] ?? null) ? $node['text'] : '';
        }

        if (($node['type'] ?? null) === 'hardBreak') {
            return "\n";
        }

        $text = '';

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $text .= self::inline($child);
            }
        }

        return $text;
    }
}
