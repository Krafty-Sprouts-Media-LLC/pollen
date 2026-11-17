<?php

/**
 * Pollen: writing one share for one channel.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Networks\Limits;
use KraftySprouts\Pollen\Networks\Network;
use KraftySprouts\Pollen\Networks\Post;
use KraftySprouts\Pollen\Settings;

/**
 * Writes the post one channel gets: picks the template (the writer's words,
 * an automation's, the channel's, the network's or Pollen's), fills it, tags
 * the link, decides where the link goes, and fits it to the network.
 *
 * @since 1.0.0
 */
final readonly class Composer
{
    /**
     * @since 1.0.0
     */
    public function __construct(
        private Settings $settings,
        private string $siteName,
    ) {}

    /**
     * Returns the post for a channel: for an item, or for a message of its own when $item is null.
     *
     * @param  string  $message  the writer's own words, which may use the variables
     * @param  string  $template  an automation's template, used before the channel's
     *
     * @since 1.0.0
     */
    public function compose(Channel $channel, Network $network, ?ItemFacts $item, string $message = '', string $template = '', string $imageUrl = '', string $firstComment = '', string $key = ''): Post
    {
        $limits = $network->limits();
        $networkText = $this->settings->networkText($network->key());
        $mode = $this->linkMode($channel, $limits, $item);
        $link = $item === null || $item->url === '' ? '' : $this->tag($item, $channel, $network);

        $values = $this->values($item, $networkText['hashtags']) + [
            'link' => in_array($mode, [Post::LINK_TEXT], true) ? $link : '',
            'site' => $this->siteName,
        ];

        $chosen = trim($message) !== '' ? $message : (trim($template) !== '' ? $template : $channel->option('template', $networkText['text']));
        $text = Template::render($chosen, $values);

        if ($mode === Post::LINK_TEXT && $link !== '' && ! str_contains($text, $link)) {
            $text = trim($text."\n\n".$link);
        }

        $comment = trim($firstComment) !== '' ? $firstComment : $channel->option('first_comment', $networkText['first_comment']);
        $comment = Template::render($comment, ['link' => $link] + $values);

        if ($mode === Post::LINK_COMMENT && $link !== '' && ! str_contains($comment, $link)) {
            $comment = trim($comment."\n".$link);
        }

        return new Post(
            text: Fitter::fit($text, $limits->maxLength, $limits->linkWeight),
            link: $mode === Post::LINK_NONE ? '' : $link,
            linkMode: $mode,
            imageUrl: $limits->image === Limits::IMAGE_NONE || $channel->option('image') === 'none' ? '' : $imageUrl,
            firstComment: $limits->firstComment ? Fitter::fit($comment, $limits->maxLength, $limits->linkWeight) : '',
            title: $item->title ?? '',
            excerpt: $item->excerpt ?? '',
            extra: $channel->option('extra'),
            key: $key,
            language: $item->language ?? '',
        );
    }

    /**
     * Returns where the link goes for this channel: a card only where the network makes one, the comment only where it takes one.
     */
    private function linkMode(Channel $channel, Limits $limits, ?ItemFacts $item): string
    {
        if ($item === null || $item->url === '') {
            return Post::LINK_NONE;
        }

        $mode = $channel->option('link', Post::LINK_CARD);

        if ($mode === Post::LINK_CARD && ! $limits->linkCard) {
            return Post::LINK_TEXT;
        }

        if ($mode === Post::LINK_COMMENT && ! $limits->firstComment) {
            return Post::LINK_TEXT;
        }

        return in_array($mode, [Post::LINK_CARD, Post::LINK_TEXT, Post::LINK_COMMENT, Post::LINK_NONE], true) ? $mode : Post::LINK_CARD;
    }

    /**
     * Returns the item's address with Pollen's campaign tags, when tagging is on.
     */
    private function tag(ItemFacts $item, Channel $channel, Network $network): string
    {
        $utm = $this->settings->utm();

        if ($utm === null) {
            return $item->url;
        }

        return $utm->apply($item->url, [
            'network' => $network->key(),
            'channel' => $channel->name,
            'slug' => $item->slug,
            'id' => $item->id,
            'type' => $item->type,
        ]);
    }

    /**
     * Returns the template variables an item gives, all empty for a message of its own.
     *
     * @return array<string, string>
     */
    private function values(?ItemFacts $item, int $hashtags): array
    {
        if ($item === null) {
            return ['title' => '', 'excerpt' => '', 'content' => '', 'author' => '', 'hashtags' => '', 'categories' => '', 'tags' => '', 'type' => ''];
        }

        return [
            'title' => $item->title,
            'excerpt' => $item->excerpt,
            'content' => TiptapText::cut(TiptapText::plain($item->body), 2000),
            'author' => $item->author,
            'hashtags' => implode(' ', Hashtags::from([...$item->categories(), ...$item->tags()], max(0, $hashtags))),
            'categories' => implode(', ', array_map(fn (string $s): string => str_replace('-', ' ', $s), $item->categories())),
            'tags' => implode(', ', array_map(fn (string $s): string => str_replace('-', ' ', $s), $item->tags())),
            'type' => $item->type,
        ];
    }
}
