<?php

/**
 * Pollen: posting to Bluesky.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use KraftySprouts\Pollen\Channels\Channel;

/**
 * Posts to a Bluesky account through the AT Protocol with an app password:
 * a session, then a post record whose links and hashtags are marked as
 * facets by their byte positions, and the link as an external card.
 *
 * @since 1.0.0
 */
final readonly class Bluesky implements TokenNetwork
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Http $http) {}

    public function key(): string
    {
        return 'bluesky';
    }

    public function label(): string
    {
        return __('Bluesky');
    }

    public function limits(): Limits
    {
        return new Limits(maxLength: 300, image: Limits::IMAGE_NONE, firstComment: true, linkCard: true);
    }

    public function connect(Channel $channel, string $token): Connected
    {
        $session = $this->session($channel, ['password' => trim($token)]);

        return new Connected(['password' => trim($token)], (string) $session['did'], '@'.(string) ($session['handle'] ?? $channel->handle));
    }

    public function publish(Channel $channel, array $credentials, Post $post): Sent
    {
        $session = $this->session($channel, $credentials);
        $headers = ['Authorization' => 'Bearer '.(string) $session['accessJwt']];
        $record = self::record($post->text, $post->language);

        if ($post->link !== '' && $post->linkMode === Post::LINK_CARD) {
            $record['embed'] = [
                '$type' => 'app.bsky.embed.external',
                'external' => ['uri' => $post->link, 'title' => mb_substr($post->title, 0, 300), 'description' => mb_substr($post->excerpt, 0, 1000)],
            ];
        }

        $made = $this->http->postJson($this->host($channel).'/xrpc/com.atproto.repo.createRecord', [
            'repo' => (string) $session['did'],
            'collection' => 'app.bsky.feed.post',
            'record' => $record,
        ], $headers);

        $uri = (string) ($made['uri'] ?? '');
        $cid = (string) ($made['cid'] ?? '');

        if ($uri === '') {
            throw ShareFailed::final(__('Bluesky didn’t say which post it made.'));
        }

        $note = '';

        if ($post->firstComment !== '') {
            try {
                $reply = self::record($post->firstComment, $post->language);
                $reply['reply'] = ['root' => ['uri' => $uri, 'cid' => $cid], 'parent' => ['uri' => $uri, 'cid' => $cid]];
                $this->http->postJson($this->host($channel).'/xrpc/com.atproto.repo.createRecord', ['repo' => (string) $session['did'], 'collection' => 'app.bsky.feed.post', 'record' => $reply], $headers);
            } catch (ShareFailed $failed) {
                $note = __('The first comment wasn’t posted: :reason', ['reason' => $failed->getMessage()]);
            }
        }

        $handle = ltrim($channel->accountName !== '' ? $channel->accountName : $channel->handle, '@');
        $rkey = substr($uri, (int) strrpos($uri, '/') + 1);

        return new Sent($uri, 'https://bsky.app/profile/'.$handle.'/post/'.$rkey, $note);
    }

    /**
     * Returns a post record for text, with its links and hashtags marked.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public static function record(string $text, string $language = ''): array
    {
        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];

        $facets = self::facets($text);

        if ($facets !== []) {
            $record['facets'] = $facets;
        }

        if ($language !== '') {
            $record['langs'] = [$language];
        }

        return $record;
    }

    /**
     * Returns the facets marking each link and hashtag in the text, by UTF-8 byte offsets.
     *
     * @return list<array<string, mixed>>
     *
     * @since 1.0.0
     */
    public static function facets(string $text): array
    {
        $facets = [];

        preg_match_all('#https?://[^\s<>"]+[^\s<>".,;:!?)\]]#u', $text, $links, PREG_OFFSET_CAPTURE);

        foreach ($links[0] as [$uri, $start]) {
            $facets[] = ['index' => ['byteStart' => $start, 'byteEnd' => $start + strlen($uri)], 'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $uri]]];
        }

        preg_match_all('/(?<![\p{L}\p{N}#\/])#([\p{L}\p{N}_]*\p{L}[\p{L}\p{N}_]*)/u', $text, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as $i => [$whole, $start]) {
            $facets[] = ['index' => ['byteStart' => $start, 'byteEnd' => $start + strlen($whole)], 'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tags[1][$i][0]]]];
        }

        return $facets;
    }

    /**
     * Returns a new session for the channel's handle and app password.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     *
     * @throws ShareFailed
     */
    private function session(Channel $channel, array $credentials): array
    {
        $handle = ltrim(trim($channel->handle), '@');

        if ($handle === '' || ($credentials['password'] ?? '') === '') {
            throw ShareFailed::reconnect(__('Give the Bluesky handle and an app password, made under Settings › Privacy and security › App passwords.'));
        }

        $session = $this->http->postJson($this->host($channel).'/xrpc/com.atproto.server.createSession', [
            'identifier' => $handle,
            'password' => (string) $credentials['password'],
        ]);

        if (! isset($session['accessJwt'], $session['did'])) {
            throw ShareFailed::reconnect(__('Bluesky didn’t accept the handle and app password.'));
        }

        return $session;
    }

    /**
     * Returns the account's server, bsky.social unless the channel names its own.
     */
    private function host(Channel $channel): string
    {
        return 'https://'.$channel->serverHost('bsky.social');
    }
}
