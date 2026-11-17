<?php

/**
 * Pollen: a sign-in's outcome.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Networks;

/**
 * What connecting a channel gave: the credentials to seal, the account the
 * channel posts as, when the access expires, and the accounts to choose from
 * when the sign-in reaches several and the channel named none of them.
 *
 * @since 1.0.0
 */
final readonly class Connected
{
    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<array{id: string, name: string}>  $choices
     *
     * @since 1.0.0
     */
    public function __construct(
        public array $credentials,
        public string $accountId = '',
        public string $accountName = '',
        public ?int $expiresAt = null,
        public array $choices = [],
        public array $options = [],
    ) {}

    /**
     * Returns whether the channel still has to name one of several accounts.
     *
     * @since 1.0.0
     */
    public function mustChoose(): bool
    {
        return $this->accountId === '' && $this->choices !== [];
    }

    /**
     * Returns the account among $choices that a channel's own account field names, by ID or name, or null.
     *
     * @param  list<array{id: string, name: string}>  $choices
     * @return array{id: string, name: string}|null
     *
     * @since 1.0.0
     */
    public static function pick(array $choices, string $wanted): ?array
    {
        $wanted = trim($wanted);

        if ($wanted === '') {
            return count($choices) === 1 ? $choices[0] : null;
        }

        foreach ($choices as $choice) {
            if ($choice['id'] === $wanted || mb_strtolower($choice['name']) === mb_strtolower(ltrim($wanted, '@'))) {
                return $choice;
            }
        }

        return null;
    }
}
