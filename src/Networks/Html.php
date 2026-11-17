<?php

/**
 * Pollen: a share written as a short HTML post, for blogs.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * Writes a share as the body of a blog post: its image, its words a
 * paragraph each, and a link back to the original. Every word is escaped.
 *
 * @since 1.0.0
 */
final class Html
{
    /**
     * Returns the post's body as HTML.
     *
     * @since 1.0.0
     */
    public static function article(Post $post): string
    {
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = $post->imageUrl !== '' ? '<p><img src="'.$e($post->imageUrl).'" alt="'.$e($post->title).'"></p>' : '';
        $text = $post->link !== '' ? str_replace($post->link, '', $post->text) : $post->text;

        foreach (preg_split("/\n{2,}/", trim($text)) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $html .= '<p>'.nl2br($e(trim($paragraph)), false).'</p>';
            }
        }

        if ($post->link !== '') {
            $html .= '<p><a href="'.$e($post->link).'" rel="canonical">'.$e(__('Read “:title” where it was first published', ['title' => $post->title !== '' ? $post->title : $post->link])).'</a></p>';
        }

        return $html;
    }
}
