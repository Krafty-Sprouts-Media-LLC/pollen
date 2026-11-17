<?php

/**
 * Pollen: calling a network through Teme's outbound door.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

use Teme\Contracts\Http\AnswerTooLarge;
use Teme\Contracts\Http\HostRefused;
use Teme\Contracts\Http\HttpAnswer;
use Teme\Contracts\Http\OutboundHttp;
use Teme\Contracts\Http\RequestFailed;

/**
 * Sends a network's requests through the outbound door and sorts every
 * answer that isn't a success into a ShareFailed: busy or down to try again,
 * a refused sign-in to connect again, anything else to give up on.
 *
 * @since 1.0.0
 */
final readonly class Http
{
    /** The seconds Pollen gives a network to answer. */
    private const SECONDS = 10;

    /**
     * @since 1.0.0
     */
    public function __construct(private OutboundHttp $outbound) {}

    /**
     * Returns a GET's decoded JSON answer.
     *
     * @param  array<string, string>  $headers
     * @return array<mixed>
     *
     * @throws ShareFailed when the network refuses or can't be reached
     *
     * @since 1.0.0
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->decode($this->call(fn (): HttpAnswer => $this->outbound->get($url, $headers, self::SECONDS)));
    }

    /**
     * Returns a JSON POST's decoded JSON answer.
     *
     * @param  array<mixed>  $json
     * @param  array<string, string>  $headers
     * @return array<mixed>
     *
     * @throws ShareFailed when the network refuses or can't be reached
     *
     * @since 1.0.0
     */
    public function postJson(string $url, array $json, array $headers = []): array
    {
        return $this->decode($this->call(fn (): HttpAnswer => $this->outbound->postJson($url, $json, $headers, self::SECONDS)));
    }

    /**
     * Returns a form POST's decoded JSON answer.
     *
     * @param  array<string, string|list<string>>  $fields
     * @param  array<string, string>  $headers
     * @return array<mixed>
     *
     * @throws ShareFailed when the network refuses or can't be reached
     *
     * @since 1.0.0
     */
    public function postForm(string $url, array $fields, array $headers = []): array
    {
        return $this->decode($this->call(fn (): HttpAnswer => $this->outbound->postForm($url, $fields, $headers, self::SECONDS)));
    }

    /**
     * Returns a JSON POST's raw answer, for networks that put the new post's ID in a header.
     *
     * @param  array<mixed>  $json
     * @param  array<string, string>  $headers
     *
     * @throws ShareFailed when the network refuses or can't be reached
     *
     * @since 1.0.0
     */
    public function postJsonAnswer(string $url, array $json, array $headers = []): HttpAnswer
    {
        return $this->call(fn (): HttpAnswer => $this->outbound->postJson($url, $json, $headers, self::SECONDS));
    }

    /**
     * Returns a GET's raw body, such as an image's bytes.
     *
     * @throws ShareFailed when the host refuses or can't be reached
     *
     * @since 1.0.0
     */
    public function bytes(string $url): string
    {
        return $this->call(fn (): HttpAnswer => $this->outbound->get($url, [], self::SECONDS))->body;
    }

    /**
     * Returns the answer of one call, or throws the failure it amounts to.
     *
     * @param  callable(): HttpAnswer  $send
     *
     * @throws ShareFailed
     */
    private function call(callable $send): HttpAnswer
    {
        try {
            $answer = $send();
        } catch (HostRefused) {
            throw ShareFailed::final(__('Teme refused to reach this network’s address. Check that it is agreed to in Settings › Pollen.'));
        } catch (AnswerTooLarge) {
            throw ShareFailed::final(__('The network’s answer was too large to read.'));
        } catch (RequestFailed) {
            throw ShareFailed::retry(__('The network couldn’t be reached.'));
        }

        if ($answer->status >= 200 && $answer->status < 300) {
            return $answer;
        }

        $said = self::said($answer);

        if ($answer->status === 401) {
            throw ShareFailed::reconnect($said !== '' ? $said : __('The network no longer accepts this channel’s sign-in.'));
        }

        if ($answer->status === 429) {
            $header = $answer->headers['retry-after'] ?? $answer->headers['Retry-After'] ?? 0;
            $wait = (int) (is_array($header) ? ($header[0] ?? 0) : $header);

            throw ShareFailed::retry($said !== '' ? $said : __('The network asked Pollen to slow down.'), max(60, $wait));
        }

        if ($answer->status >= 500 || $answer->status === 408) {
            throw ShareFailed::retry($said !== '' ? $said : __('The network had a problem. Pollen will try again.'));
        }

        throw ShareFailed::final($said !== '' ? $said : __('The network refused the share (:status).', ['status' => $answer->status]));
    }

    /**
     * Returns the answer's body as JSON, an empty array when it has none.
     *
     * @return array<mixed>
     */
    private function decode(HttpAnswer $answer): array
    {
        $decoded = json_decode($answer->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns the network's own words about a failure, in short, from the usual places networks put them.
     *
     * @since 1.0.0
     */
    public static function said(HttpAnswer $answer): string
    {
        $body = json_decode($answer->body, true);

        if (! is_array($body)) {
            return mb_substr(trim(strip_tags($answer->body)), 0, 200);
        }

        $candidates = [
            $body['error']['message'] ?? null,
            $body['error']['error_msg'] ?? null,
            $body['error_description'] ?? null,
            $body['detail'] ?? null,
            $body['description'] ?? null,
            $body['message'] ?? null,
            $body['errors'][0]['message'] ?? null,
            $body['errors'][0]['detail'] ?? null,
            $body['json']['errors'][0][1] ?? null,
            is_string($body['error'] ?? null) ? $body['error'] : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_substr(trim($candidate), 0, 200);
            }
        }

        return '';
    }
}
