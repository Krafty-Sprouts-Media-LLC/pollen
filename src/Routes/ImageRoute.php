<?php

/**
 * Pollen: the address prepared image copies are served from.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Routes;

use KraftySprouts\Pollen\Store\Images;
use Teme\Contracts\Routes\PublicRequest;
use Teme\Contracts\Routes\PublicResponse;
use Teme\Contracts\Routes\PublicRoute;

/**
 * Answers /pollen/image/{key}.jpg with a copy Pollen prepared for a network,
 * so a network that fetches images by address can take it. Keys are 40
 * random characters; an unknown or expired key answers 404.
 *
 * @since 1.0.0
 */
final readonly class ImageRoute implements PublicRoute
{
    public function handle(PublicRequest $request): PublicResponse
    {
        $key = (string) preg_replace('/\.(jpg|png)$/', '', (string) ($request->parameters['key'] ?? ''));
        $image = preg_match('/^[a-z0-9]{40}$/', $key) === 1 ? (new Images)->find($key) : null;

        if ($image === null) {
            return new PublicResponse(404, 'text/plain; charset=utf-8', 'Not found', ['x-robots-tag' => 'noindex']);
        }

        return new PublicResponse(200, $image['mime'], $image['bytes'], ['x-robots-tag' => 'noindex']);
    }
}
