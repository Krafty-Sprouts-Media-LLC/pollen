<?php

/**
 * Pollen: posting to a Facebook Page.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks\Meta;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Networks\AppKeys;
use KraftySprouts\Pollen\Networks\Connected;
use KraftySprouts\Pollen\Networks\Http;
use KraftySprouts\Pollen\Networks\Limits;
use KraftySprouts\Pollen\Networks\OAuthNetwork;
use KraftySprouts\Pollen\Networks\Post;
use KraftySprouts\Pollen\Networks\Sent;
use KraftySprouts\Pollen\Networks\ShareFailed;

/**
 * Posts to a Facebook Page through the Graph API with the Page's own token:
 * a link post with Facebook's preview, or a photo post when the link goes in
 * the text, and the first comment under it.
 *
 * @since 1.0.0
 */
final readonly class FacebookPages implements OAuthNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return __('Facebook Page');
    }

    public function app(): string
    {
        return 'facebook';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 63206, image: Limits::IMAGE_OPTIONAL, firstComment: true, linkCard: true, imageSize: [1200, 630]);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return (new MetaSignIn($this->http))->authorizeUrl($app, $redirect, $state, ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement', 'business_management']);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $signIn = new MetaSignIn($this->http);
        $pages = $signIn->pages($signIn->personToken($app, $code, $redirect));
        $choices = array_map(fn (array $p): array => ['id' => $p['id'], 'name' => $p['name']], $pages);
        $chosen = Connected::pick($choices, $channel->account);

        if ($chosen === null) {
            return new Connected(['pages' => $pages], choices: $choices);
        }

        foreach ($pages as $page) {
            if ($page['id'] === $chosen['id']) {
                return new Connected(['token' => $page['token'], 'pages' => $pages], $page['id'], $page['name']);
            }
        }

        return new Connected(['pages' => $pages], choices: $choices);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        // A Page token from a long-lived person token doesn't expire.
        return null;
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $token = (string) ($credentials['token'] ?? '');

        if ($token === '' || $channel->account === '') {
            throw ShareFailed::reconnect(__('Choose which Facebook Page this channel posts to, then connect it again.'));
        }

        $base = MetaSignIn::GRAPH.'/'.rawurlencode($channel->account);

        if ($post->imageUrl !== '' && $post->linkMode !== Post::LINK_CARD) {
            $answer = $this->http->postForm($base.'/photos', ['url' => $post->imageUrl, 'caption' => $post->text, 'access_token' => $token]);
            $id = (string) ($answer['post_id'] ?? $answer['id'] ?? '');
        } else {
            $fields = ['message' => $post->text, 'access_token' => $token];

            if ($post->link !== '' && $post->linkMode === Post::LINK_CARD) {
                $fields['link'] = $post->link;
            }

            $answer = $this->http->postForm($base.'/feed', $fields);
            $id = (string) ($answer['id'] ?? '');
        }

        if ($id === '') {
            throw ShareFailed::final(__('Facebook didn’t say which post it made.'));
        }

        $note = '';

        if ($post->firstComment !== '') {
            try {
                $this->http->postForm(MetaSignIn::GRAPH.'/'.rawurlencode($id).'/comments', ['message' => $post->firstComment, 'access_token' => $token]);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        return new Sent($id, 'https://www.facebook.com/'.$id, $note);
    }
}
