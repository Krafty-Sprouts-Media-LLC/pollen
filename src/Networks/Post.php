<?php

/**
 * Pollen: one post ready for a network.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * What a network is asked to post: the finished text, the link and how to
 * show it, the image's address, the first comment, and the item's title and
 * excerpt for networks that show a link as a card.
 *
 * @since 1.0.0
 */
final readonly class Post
{
    /** The link goes as a preview card, where the network makes one. */
    public const LINK_CARD = 'card';

    /** The link is already in the text. */
    public const LINK_TEXT = 'text';

    /** The link goes in the first comment. */
    public const LINK_COMMENT = 'comment';

    /** No link. */
    public const LINK_NONE = 'none';

    /**
     * @param  string  $text  the words, fitted to the network, with the link in them when $linkMode is text
     * @param  string  $extra  the channel's network option, such as a Reddit flair or "unlisted"
     * @param  string  $key  a key unique to this share, for networks that drop a repeated request
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $text,
        public string $link = '',
        public string $linkMode = self::LINK_CARD,
        public string $imageUrl = '',
        public string $firstComment = '',
        public string $title = '',
        public string $excerpt = '',
        public string $extra = '',
        public string $key = '',
        public string $language = '',
    ) {}
}
