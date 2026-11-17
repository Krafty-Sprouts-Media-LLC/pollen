<?php

/**
 * Pollen: the vault's refusal.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use RuntimeException;

/**
 * Thrown when the vault can't seal or open a channel's tokens: no lock key is
 * set, or the box was sealed under another one. Its message is one sentence.
 *
 * @since 1.0.0
 */
final class VaultLocked extends RuntimeException {}
