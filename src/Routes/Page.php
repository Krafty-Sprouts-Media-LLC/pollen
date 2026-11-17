<?php

/**
 * Pollen: the small pages Pollen's sign-in addresses answer with.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Routes;

use Teme\Contracts\Routes\PublicResponse;

/**
 * Draws the plain page a person sees at the end of a sign-in, every word
 * escaped, kept out of search engines, with a way back to the admin.
 *
 * @since 1.0.0
 */
final class Page
{
    /**
     * Returns a page with a heading and lines.
     *
     * @param  list<string>  $lines
     *
     * @since 1.0.0
     */
    public static function answer(int $status, string $heading, array $lines, string $back): PublicResponse
    {
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="robots" content="noindex"><title>'.$e(__('Pollen')).' · '.$e($heading).'</title>'
            .'<style>body{font:16px/1.5 system-ui,sans-serif;max-width:36rem;margin:4rem auto;padding:0 1rem;color:#1f2a1f;background:#fbfaf5}h1{font-size:1.4rem}a{color:#2f5d34}</style></head><body>'
            .'<h1>'.$e($heading).'</h1>';

        foreach ($lines as $line) {
            $body .= '<p>'.$e($line).'</p>';
        }

        $body .= '<p><a href="'.$e($back).'">'.$e(__('Back to your site’s admin')).'</a></p></body></html>';

        return new PublicResponse($status, 'text/html; charset=utf-8', $body, ['x-robots-tag' => 'noindex']);
    }
}
