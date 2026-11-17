<?php

/**
 * Pollen: the automations table.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Store;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use KraftySprouts\Pollen\Automations\Automation;

/**
 * Reads and writes Pollen's automations: the rules that share an item when
 * it is published, and the plans that re-share older items again and again.
 *
 * @since 1.0.0
 */
final class Automations
{
    private const TABLE = 'pollen_automations';

    /**
     * Returns the automation with this ID, or null.
     *
     * @since 1.0.0
     */
    public function find(string $id): ?Automation
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : Automation::fromRow((array) $row);
    }

    /**
     * Returns the active automations of a kind.
     *
     * @return list<Automation>
     *
     * @since 1.0.0
     */
    public function active(string $kind): array
    {
        return array_values(array_map(fn (object $r): Automation => Automation::fromRow((array) $r), DB::table(self::TABLE)->where('active', true)->where('kind', $kind)->orderBy('created_at')->get()->all()));
    }

    /**
     * Returns the active re-share automations whose next run is due.
     *
     * @return list<Automation>
     *
     * @since 1.0.0
     */
    public function due(): array
    {
        return array_values(array_map(
            fn (object $r): Automation => Automation::fromRow((array) $r),
            DB::table(self::TABLE)->where('active', true)->where('kind', Automation::RESHARE)->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))->get()->all(),
        ));
    }

    /**
     * Stores an automation from its form and returns its ID.
     *
     * @param  array<string, mixed>  $settings
     *
     * @since 1.0.0
     */
    public function save(?string $id, string $name, string $kind, array $settings, string $personId): string
    {
        $row = ['name' => $name, 'kind' => $kind, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR), 'updated_at' => now()];

        if ($id === null) {
            $id = Ids::make();
            DB::table(self::TABLE)->insert([...$row, 'id' => $id, 'created_by' => $personId, 'created_at' => now()]);
        } else {
            DB::table(self::TABLE)->where('id', $id)->update([...$row, 'next_run_at' => null]);
        }

        return $id;
    }

    /**
     * Records a run: when the next is due, whose turn is next, and a note for the table.
     *
     * @since 1.0.0
     */
    public function ran(string $id, ?DateTimeInterface $next, int $turn, string $note): void
    {
        DB::table(self::TABLE)->where('id', $id)->update(['next_run_at' => $next, 'turn' => $turn, 'last_note' => mb_substr($note, 0, 500), 'updated_at' => now()]);
    }

    /**
     * Switches automations on or off.
     *
     * @param  list<string>  $ids
     *
     * @since 1.0.0
     */
    public function setActive(array $ids, bool $active): int
    {
        return DB::table(self::TABLE)->whereIn('id', $ids)->update(['active' => $active, 'next_run_at' => null, 'updated_at' => now()]);
    }

    /**
     * Deletes automations and their shares that haven't gone.
     *
     * @param  list<string>  $ids
     *
     * @since 1.0.0
     */
    public function delete(array $ids): int
    {
        DB::table('pollen_shares')->whereIn('automation_id', $ids)->whereIn('status', [Shares::PLANNED, Shares::SUGGESTED, Shares::HELD])->delete();

        return DB::table(self::TABLE)->whereIn('id', $ids)->delete();
    }
}
