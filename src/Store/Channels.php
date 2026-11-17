<?php

/**
 * Pollen: the channels table.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Networks\Connected;

/**
 * Reads and writes Pollen's channels: finding them by ID, name or label,
 * keeping their sealed sign-ins, and marking them connected, waiting for a
 * choice of account, or needing to be connected again.
 *
 * @since 1.0.0
 */
final class Channels
{
    private const TABLE = 'pollen_channels';

    /**
     * Returns the channel with this ID, or null.
     *
     * @since 1.0.0
     */
    public function find(string $id): ?Channel
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : self::channel((array) $row);
    }

    /**
     * Returns the channel whose connect key this is, or null.
     *
     * @since 1.0.0
     */
    public function byConnectKey(string $key): ?Channel
    {
        $row = DB::table(self::TABLE)->where('connect_key', $key)->first();

        return $row === null ? null : self::channel((array) $row);
    }

    /**
     * Returns every channel, by name.
     *
     * @return list<Channel>
     *
     * @since 1.0.0
     */
    public function all(): array
    {
        return array_values(array_map(fn (object $row): Channel => self::channel((array) $row), DB::table(self::TABLE)->orderBy('name')->get()->all()));
    }

    /**
     * Returns the channels that $words names, by name or label, separated by commas; every channel when $words is empty.
     * Unknown words come back in $unknown.
     *
     * @param  list<string>  $unknown
     * @return list<Channel>
     *
     * @since 1.0.0
     */
    public function named(string $words, array &$unknown = []): array
    {
        $all = $this->all();
        $wanted = array_values(array_filter(array_map(fn (string $w): string => mb_strtolower(trim($w)), explode(',', $words)), fn (string $w): bool => $w !== ''));
        $unknown = [];

        if ($wanted === [] || in_array('all', $wanted, true)) {
            return $all;
        }

        $found = [];

        foreach ($wanted as $word) {
            $hit = false;

            foreach ($all as $channel) {
                $labels = array_map('mb_strtolower', $channel->labels);

                if (mb_strtolower($channel->name) === $word || $channel->id === $word || in_array($word, $labels, true) || $channel->network === $word) {
                    $found[$channel->id] = $channel;
                    $hit = true;
                }
            }

            if (! $hit) {
                $unknown[] = $word;
            }
        }

        return array_values($found);
    }

    /**
     * Returns the channels that share new items of this type.
     *
     * @return list<Channel>
     *
     * @since 1.0.0
     */
    public function forNewItems(string $type): array
    {
        return array_values(array_filter($this->all(), fn (Channel $c): bool => $c->ready() && in_array($type, $c->autoTypes, true)));
    }

    /**
     * Stores a channel's values from its form and returns its ID.
     *
     * @param  array<string, mixed>  $values
     *
     * @since 1.0.0
     */
    public function save(?string $id, array $values, string $personId): string
    {
        $now = now();
        $row = [...$values, 'updated_at' => $now];

        foreach (['labels', 'auto_types', 'people', 'options'] as $json) {
            if (array_key_exists($json, $row)) {
                $row[$json] = json_encode($row[$json], JSON_THROW_ON_ERROR);
            }
        }

        if ($id === null) {
            $id = Ids::make();
            DB::table(self::TABLE)->insert([...$row, 'id' => $id, 'created_by' => $personId, 'created_at' => $now]);
        } else {
            DB::table(self::TABLE)->where('id', $id)->update($row);
        }

        return $id;
    }

    /**
     * Records a sign-in: the sealed credentials, the account, and whether a choice of account waits.
     *
     * @since 1.0.0
     */
    public function connected(Channel $channel, Connected $connected, Vault $vault): void
    {
        $options = [...$channel->options, ...$connected->options];

        if ($connected->choices !== []) {
            $options['choices'] = $connected->choices;
        }

        DB::table(self::TABLE)->where('id', $channel->id)->update([
            'sealed' => $vault->seal($connected->credentials),
            'account' => $connected->accountId !== '' ? $connected->accountId : $channel->account,
            'account_name' => $connected->accountName,
            'token_expires_at' => $connected->expiresAt === null ? null : (new DateTimeImmutable('@'.$connected->expiresAt)),
            'status' => $connected->mustChoose() ? Channel::CHOOSE : Channel::CONNECTED,
            'options' => json_encode($options, JSON_THROW_ON_ERROR),
            'failures' => 0,
            'last_error' => '',
            'connect_key' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Marks the channel as needing to be connected again, with the network's words, and gives it a new connect key.
     * Returns whether it was connected before, so the people who manage Pollen are told once.
     *
     * @since 1.0.0
     */
    public function needsReconnect(Channel $channel, string $why): bool
    {
        DB::table(self::TABLE)->where('id', $channel->id)->update([
            'status' => Channel::RECONNECT,
            'last_error' => mb_substr($why, 0, 500),
            'connect_key' => Ids::key(),
            'updated_at' => now(),
        ]);

        return $channel->status !== Channel::RECONNECT;
    }

    /**
     * Gives the channel a new connect key, for its Sign-in column, and returns it.
     *
     * @since 1.0.0
     */
    public function newConnectKey(string $id): string
    {
        $key = Ids::key();
        DB::table(self::TABLE)->where('id', $id)->update(['connect_key' => $key, 'updated_at' => now()]);

        return $key;
    }

    /**
     * Counts one more failure in a row and returns the count.
     *
     * @since 1.0.0
     */
    public function failed(string $id, string $why): int
    {
        DB::table(self::TABLE)->where('id', $id)->update(['failures' => DB::raw('failures + 1'), 'last_error' => mb_substr($why, 0, 500), 'updated_at' => now()]);

        return (int) DB::table(self::TABLE)->where('id', $id)->value('failures');
    }

    /**
     * Clears the count of failures after a share went through.
     *
     * @since 1.0.0
     */
    public function succeeded(string $id): void
    {
        DB::table(self::TABLE)->where('id', $id)->where('failures', '>', 0)->update(['failures' => 0, 'last_error' => '']);
    }

    /**
     * Switches channels on or off.
     *
     * @param  list<string>  $ids
     *
     * @since 1.0.0
     */
    public function setActive(array $ids, bool $active): int
    {
        return DB::table(self::TABLE)->whereIn('id', $ids)->update(['active' => $active, 'updated_at' => now()]);
    }

    /**
     * Deletes channels and their shares that haven't gone.
     *
     * @param  list<string>  $ids
     *
     * @since 1.0.0
     */
    public function delete(array $ids): int
    {
        DB::table('pollen_shares')->whereIn('channel_id', $ids)->whereNotIn('status', ['sent', 'failed'])->delete();

        return DB::table(self::TABLE)->whereIn('id', $ids)->delete();
    }

    /**
     * Returns how many channels are working and how many need someone.
     *
     * @return array{working: int, attention: int}
     *
     * @since 1.0.0
     */
    public function health(): array
    {
        $working = DB::table(self::TABLE)->where('active', true)->where('status', Channel::CONNECTED)->count();
        $attention = DB::table(self::TABLE)->where('active', true)->where('status', '!=', Channel::CONNECTED)->count();

        return ['working' => $working, 'attention' => $attention];
    }

    /**
     * Returns the channels whose sign-in expires within $seconds.
     *
     * @return list<Channel>
     *
     * @since 1.0.0
     */
    public function expiringWithin(int $seconds): array
    {
        return array_values(array_map(
            fn (object $row): Channel => self::channel((array) $row),
            DB::table(self::TABLE)->where('status', Channel::CONNECTED)->whereNotNull('token_expires_at')->where('token_expires_at', '<=', now()->addSeconds($seconds))->get()->all(),
        ));
    }

    /**
     * Returns a channel from its row.
     *
     * @param  array<string, mixed>  $row
     *
     * @since 1.0.0
     */
    public static function channel(array $row): Channel
    {
        $json = fn (string $key): array => is_array($decoded = json_decode((string) ($row[$key] ?? '[]'), true)) ? $decoded : [];
        $expires = $row['token_expires_at'] ?? null;

        return new Channel(
            id: (string) $row['id'],
            name: (string) $row['name'],
            network: (string) $row['network'],
            account: (string) $row['account'],
            accountName: (string) $row['account_name'],
            labels: array_values(array_map('strval', $json('labels'))),
            autoTypes: array_values(array_map('strval', $json('auto_types'))),
            server: (string) $row['server'],
            handle: (string) $row['handle'],
            sealed: (string) $row['sealed'],
            tokenExpiresAt: $expires === null ? null : (int) strtotime((string) $expires),
            options: $json('options'),
            status: (string) $row['status'],
            active: (bool) $row['active'],
            failures: (int) $row['failures'],
            lastError: (string) $row['last_error'],
            access: (string) $row['access'],
            people: array_values(array_map('strval', $json('people'))),
            createdBy: (string) $row['created_by'],
        );
    }
}
