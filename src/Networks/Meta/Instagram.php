<?php

/**
 * Pollen: posting to an Instagram business account.
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
 * Posts an image with its caption to an Instagram professional account linked
 * to a Facebook Page, through Instagram's content publishing API: a media
 * container from the image's address, then its publication. Instagram never
 * makes links in captions clickable, so the link goes in the first comment.
 *
 * @since 1.0.0
 */
final readonly class Instagram implements OAuthNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return __('Instagram');
    }

    public function app(): string
    {
        return 'facebook';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 2200, image: Limits::IMAGE_REQUIRED, firstComment: true, linkCard: false, imageSize: [1080, 1350]);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return (new MetaSignIn($this->http))->authorizeUrl($app, $redirect, $state, ['pages_show_list', 'pages_read_engagement', 'business_management', 'instagram_basic', 'instagram_content_publish']);
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $signIn = new MetaSignIn($this->http);
        $token = $signIn->personToken($app, $code, $redirect);
        $choices = [];

        foreach ($signIn->pages($token) as $page) {
            if ($page['instagram'] !== '') {
                $choices[] = ['id' => $page['instagram'], 'name' => $page['instagramName'] !== '' ? $page['instagramName'] : $page['name']];
            }
        }

        if ($choices === []) {
            throw ShareFailed::final(__('None of your Facebook Pages has an Instagram professional account linked to it.'));
        }

        $chosen = Connected::pick($choices, $channel->account);

        return $chosen === null
            ? new Connected(['token' => $token], choices: $choices)
            : new Connected(['token' => $token], $chosen['id'], $chosen['name']);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        return null;
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $token = (string) ($credentials['token'] ?? '');

        if ($token === '' || $channel->account === '') {
            throw ShareFailed::reconnect(__('Choose which Instagram account this channel posts to, then connect it again.'));
        }

        if ($post->imageUrl === '') {
            throw ShareFailed::final(__('Instagram needs an image, and this share has none.'));
        }

        $base = MetaSignIn::GRAPH.'/'.rawurlencode($channel->account);
        $container = $this->http->postForm($base.'/media', ['image_url' => $post->imageUrl, 'caption' => $post->text, 'access_token' => $token]);
        $creation = (string) ($container['id'] ?? '');

        if ($creation === '') {
            throw ShareFailed::final(__('Instagram didn’t take the image.'));
        }

        $published = $this->http->postForm($base.'/media_publish', ['creation_id' => $creation, 'access_token' => $token]);
        $id = (string) ($published['id'] ?? '');

        if ($id === '') {
            throw ShareFailed::retry(__('Instagram was still preparing the image. Pollen will try again.'));
        }

        $url = '';
        $note = '';

        try {
            $url = (string) ($this->http->get(MetaSignIn::GRAPH.'/'.rawurlencode($id).'?'.http_build_query(['fields' => 'permalink', 'access_token' => $token]))['permalink'] ?? '');
        } catch (ShareFailed) {
            // The post stands without its address.
        }

        if ($post->firstComment !== '') {
            try {
                $this->http->postForm(MetaSignIn::GRAPH.'/'.rawurlencode($id).'/comments', ['message' => $post->firstComment, 'access_token' => $token]);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        return new Sent($id, $url, $note);
    }
}
