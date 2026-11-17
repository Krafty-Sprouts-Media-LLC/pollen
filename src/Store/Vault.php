<?php

/**
 * Pollen: the sealed box channel tokens are kept in.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

/**
 * Seals a channel's tokens with libsodium's secret box, under a key made from
 * Pollen's lock key, a secret setting only an administrator types. A plugin
 * can't write secret settings itself, so tokens a sign-in returns are kept
 * sealed in Pollen's own table instead, never in plain text.
 *
 * @since 1.0.0
 */
final readonly class Vault
{
    /** The shortest lock key Pollen accepts. */
    public const SHORTEST_KEY = 24;

    /**
     * @since 1.0.0
     */
    public function __construct(private string $lockKey) {}

    /**
     * Returns whether a lock key is set and long enough to seal with.
     *
     * @since 1.0.0
     */
    public function ready(): bool
    {
        return mb_strlen(trim($this->lockKey)) >= self::SHORTEST_KEY;
    }

    /**
     * Returns the values sealed, as base64 text safe to store.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws VaultLocked when no lock key is set
     *
     * @since 1.0.0
     */
    public function seal(array $values): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = sodium_crypto_secretbox((string) json_encode($values, JSON_THROW_ON_ERROR), $nonce, $this->key());

        return 'v1.'.base64_encode($nonce.$box);
    }

    /**
     * Returns the values in a sealed box.
     *
     * @return array<string, mixed>
     *
     * @throws VaultLocked when no lock key is set, or the key isn't the one that sealed it
     *
     * @since 1.0.0
     */
    public function open(string $sealed): array
    {
        if ($sealed === '') {
            return [];
        }

        $raw = base64_decode(substr($sealed, 3), true);

        if (! str_starts_with($sealed, 'v1.') || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new VaultLocked(__('A channel’s saved sign-in is damaged. Connect the channel again.'));
        }

        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());

        if ($plain === false) {
            throw new VaultLocked(__('Pollen’s lock key changed since this channel was connected. Connect the channel again.'));
        }

        $values = json_decode($plain, true);

        return is_array($values) ? $values : [];
    }

    /**
     * Returns the secret box key made from the lock key.
     */
    private function key(): string
    {
        if (! $this->ready()) {
            throw new VaultLocked(__('Set Pollen’s lock key in Settings › Pollen before connecting channels.'));
        }

        return sodium_crypto_generichash('pollen-vault-v1|'.trim($this->lockKey), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
