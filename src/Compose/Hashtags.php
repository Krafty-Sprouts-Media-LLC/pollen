<?php

/**
 * Pollen: hashtags made from an item's terms.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

/**
 * Turns term slugs or names into hashtags: each word capitalised and joined,
 * only letters and digits kept, duplicates dropped, at most a set number.
 *
 * @since 1.0.0
 */
final class Hashtags
{
    /**
     * Returns up to $most hashtags from these terms, in their order.
     *
     * @param  list<string>  $terms
     * @return list<string>
     *
     * @since 1.0.0
     */
    public static function from(array $terms, int $most = 5): array
    {
        $tags = [];

        foreach ($terms as $term) {
            $words = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tag = implode('', array_map(fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)).mb_substr($w, 1), $words));

            if ($tag !== '' && ! in_array('#'.$tag, $tags, true)) {
                $tags[] = '#'.$tag;
            }

            if (count($tags) >= $most) {
                break;
            }
        }

        return $tags;
    }
}
