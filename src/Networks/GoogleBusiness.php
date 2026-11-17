<?php

/**
 * Pollen: posting to a Google Business Profile.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Networks\Google\GoogleSignIn;

/**
 * Adds an update to one location of a Google Business Profile through the
 * Business Profile APIs: the text as its summary, a Learn more button to the
 * item, and the image by its address. Google must first grant the project
 * access to these APIs.
 *
 * @since 1.0.0
 */
final readonly class GoogleBusiness implements OAuthNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'google-business';
    }

    public function label(): string
    {
        return __('Google Business Profile');
    }

    public function app(): string
    {
        return 'google';
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 1500, image: Limits::IMAGE_OPTIONAL, firstComment: false, linkCard: true, imageSize: [1200, 900]);
    }

    public function authorizeUrl(AppKeys $app, Channel $channel, string $redirect, string $state, string $challenge): string
    {
        return GoogleSignIn::authorizeUrl($app, $redirect, $state, $challenge, 'https://www.googleapis.com/auth/business.manage');
    }

    public function exchange(AppKeys $app, Channel $channel, string $code, string $verifier, string $redirect): Connected
    {
        $credentials = (new GoogleSignIn($this->http))->exchange($app, $code, $verifier, $redirect);
        $headers = ['Authorization' => 'Bearer '.$credentials['access_token']];
        $choices = [];
        $accounts = $this->http->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts', $headers);

        foreach (is_array($accounts['accounts'] ?? null) ? $accounts['accounts'] : [] as $account) {
            $locations = $this->http->get('https://mybusinessbusinessinformation.googleapis.com/v1/'.(string) $account['name'].'/locations?readMask=name,title&pageSize=100', $headers);

            foreach (is_array($locations['locations'] ?? null) ? $locations['locations'] : [] as $location) {
                $choices[] = ['id' => (string) $account['name'].'/'.(string) $location['name'], 'name' => (string) ($location['title'] ?? $location['name'])];
            }
        }

        $chosen = Connected::pick($choices, $channel->account);

        return $chosen === null
            ? new Connected($credentials, expiresAt: $credentials['expires_at'], choices: $choices)
            : new Connected($credentials, $chosen['id'], $chosen['name'], $credentials['expires_at']);
    }

    public function refresh(AppKeys $app, Channel $channel, array $credentials): ?Connected
    {
        $renewed = (new GoogleSignIn($this->http))->refresh($app, $credentials);

        return $renewed === null ? null : new Connected($renewed, $channel->account, $channel->accountName, $renewed['expires_at']);
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $update = [
            'languageCode' => $post->language !== '' ? $post->language : 'en',
            'summary' => $post->text,
            'topicType' => 'STANDARD',
        ];

        if ($post->link !== '') {
            $update['callToAction'] = ['actionType' => 'LEARN_MORE', 'url' => $post->link];
        }

        if ($post->imageUrl !== '') {
            $update['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => $post->imageUrl]];
        }

        $answer = $this->http->postJson('https://mybusiness.googleapis.com/v4/'.$channel->account.'/localPosts', $update, ['Authorization' => 'Bearer '.(string) ($credentials['access_token'] ?? '')]);

        return new Sent((string) ($answer['name'] ?? ''), (string) ($answer['searchUrl'] ?? ''));
    }
}
