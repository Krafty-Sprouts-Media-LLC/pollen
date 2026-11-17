<?php

/**
 * Pollen: what a network allows in one post.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * A network's rules for a post: the most characters, how it counts a link,
 * whether it needs, takes or ignores an image, the image size it shows best,
 * and whether it takes a first comment and makes link cards.
 *
 * @since 1.0.0
 */
final readonly class Limits
{
    /** The network can't post without an image. */
    public const IMAGE_REQUIRED = 'required';

    /** The network takes an image when there is one. */
    public const IMAGE_OPTIONAL = 'optional';

    /** Pollen can't send this network an image. */
    public const IMAGE_NONE = 'none';

    /**
     * @param  int|null  $linkWeight  how many characters the network counts a link as; null counts its length
     * @param  array{0: int, 1: int}  $imageSize  the width and height the network shows best
     *
     * @since 1.0.0
     */
    public function __construct(
        public int $maxLength,
        public ?int $linkWeight = null,
        public string $image = self::IMAGE_OPTIONAL,
        public bool $firstComment = false,
        public bool $linkCard = true,
        public array $imageSize = [1200, 630],
        public int $titleLength = 0,
    ) {}
}
