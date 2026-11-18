<?php

/**
 * Pollen: what a share needs to know about an item.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Compose;

use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ItemSnapshot;
use Teme\Contracts\People\PeopleReader;

/**
 * An item as a share uses it: its title, address, words, terms, writer and
 * first image, read through the content door for the person the work acts
 * for, and the writer's name through the people door.
 *
 * @since 1.0.0
 */
final readonly class ItemFacts
{
    /**
     * @param  array<string, mixed>  $body  the Tiptap document
     * @param  array<string, list<string>>  $terms  term slugs by taxonomy
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $url,
        public string $slug,
        public string $language,
        public array $body,
        public array $terms,
        public string $excerpt,
        public string $author,
        public string $firstImage,
        public string $status = 'published',
    ) {}

    /**
     * Reads an item for a person who may edit it.
     *
     * @throws \Teme\Contracts\Content\ReadRefused when the person may not
     *
     * @since 1.0.0
     */
    public static function read(ContentReader $content, ?PeopleReader $people, string $itemId, string $personId, string $fallbackAuthorId = ''): self
    {
        return self::fromSnapshot($content->draft($itemId, $personId), $people, $fallbackAuthorId);
    }

    /**
     * Returns the facts in a snapshot.
     *
     * @since 1.0.0
     */
    public static function fromSnapshot(ItemSnapshot $snapshot, ?PeopleReader $people = null, string $fallbackAuthorId = ''): self
    {
        $field = $snapshot->fields['excerpt'] ?? $snapshot->fields['summary'] ?? $snapshot->fields['description'] ?? null;
        $excerpt = is_string($field) && trim($field) !== '' ? TiptapText::cut(trim($field), 300) : TiptapText::excerpt($snapshot->body, 300);
        // The item's own author is its first credited person; with no credits Teme means its owner, which a plugin can't read.
        $authorIds = $snapshot->credits !== [] ? [$snapshot->credits[0]] : array_filter([$fallbackAuthorId]);

        return new self(
            id: $snapshot->item->id,
            type: $snapshot->item->type,
            title: $snapshot->item->title,
            url: (string) $snapshot->item->url,
            slug: $snapshot->slug,
            language: $snapshot->item->language,
            body: $snapshot->body,
            terms: $snapshot->terms,
            excerpt: $excerpt,
            author: $people === null ? '' : self::names($people, array_values($authorIds)),
            firstImage: (string) TiptapText::firstImage($snapshot->body),
            status: $snapshot->item->status,
        );
    }

    /**
     * Returns the category slugs, from whichever taxonomy the site calls categories.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function categories(): array
    {
        return array_values($this->terms['category'] ?? $this->terms['categories'] ?? []);
    }

    /**
     * Returns the tag slugs, from whichever taxonomy the site calls tags.
     *
     * @return list<string>
     *
     * @since 1.0.0
     */
    public function tags(): array
    {
        return array_values($this->terms['tag'] ?? $this->terms['post_tag'] ?? $this->terms['tags'] ?? []);
    }

    /**
     * Returns the display names of these accounts, joined with "and".
     *
     * @param  list<string>  $ids
     */
    private static function names(PeopleReader $people, array $ids): string
    {
        if ($ids === []) {
            return '';
        }

        $names = [];
        $after = null;

        // A site has few writers; read at most five pages of them.
        for ($page = 0; $page < 5 && count($names) < count($ids); $page++) {
            $found = $people->writers($after, 200);

            foreach ($found->people as $person) {
                if (in_array($person->id, $ids, true)) {
                    $names[$person->id] = $person->name;
                }
            }

            if (($after = $found->next) === null) {
                break;
            }
        }

        $ordered = array_values(array_filter(array_map(fn (string $id): string => $names[$id] ?? '', $ids)));

        return count($ordered) > 1 ? implode(', ', array_slice($ordered, 0, -1)).' '.__('and').' '.end($ordered) : ($ordered[0] ?? '');
    }
}
