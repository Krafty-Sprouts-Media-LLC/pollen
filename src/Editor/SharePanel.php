<?php

/**
 * Pollen: the panel in the editor.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Editor;

use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Store\Shares;
use Teme\Contracts\Editor\EditorPanel;
use Teme\Contracts\Editor\EditorPanelContext;
use Teme\Contracts\Editor\PanelView;

/**
 * Pollen's panel in the editor: one line on what was shared of this item,
 * what waits and what failed, and whether its writer turned sharing off. Its
 * actions plan shares, ask for captions, or switch sharing off; core draws
 * them. It remembers which of Pollen's permissions the person holds, for
 * the jobs its actions start.
 *
 * @since 1.0.0
 */
final readonly class SharePanel implements EditorPanel
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    public function view(EditorPanelContext $context): PanelView
    {
        $this->doors->people->saw($context->personId, $context->permissions);

        if ($this->doors->items->optedOut($context->itemId)) {
            return new PanelView(__('Sharing is off for this item.'), 'warn');
        }

        $counts = [Shares::SENT => 0, Shares::PLANNED => 0, Shares::FAILED => 0, Shares::SUGGESTED => 0, Shares::HELD => 0];
        $last = null;

        foreach ($this->doors->shares->forItem($context->itemId) as $share) {
            $status = $share['status'] === Shares::SENDING ? Shares::PLANNED : (string) $share['status'];

            if (isset($counts[$status])) {
                $counts[$status]++;
            }

            if ($status === Shares::SENT && ($last === null || (string) $share['sent_at'] > (string) $last['sent_at'])) {
                $last = $share;
            }
        }

        if (array_sum($counts) === 0) {
            return new PanelView(__('Not shared yet. Plan shares, or let your channels share it when it is published.'), 'neutral');
        }

        $parts = [];

        $words = [
            Shares::SENT => fn (int $n): string => __('Sent :n', ['n' => $n]),
            Shares::PLANNED => fn (int $n): string => __(':n waiting', ['n' => $n]),
            Shares::SUGGESTED => fn (int $n): string => __(':n suggested in the Planner', ['n' => $n]),
            Shares::HELD => fn (int $n): string => __(':n held', ['n' => $n]),
            Shares::FAILED => fn (int $n): string => __(':n failed', ['n' => $n]),
        ];

        foreach ($words as $status => $say) {
            if ($counts[$status] > 0) {
                $parts[] = $say($counts[$status]);
            }
        }

        $line = implode(' · ', $parts);

        if ($last !== null) {
            $channel = $this->doors->channels->find((string) $last['channel_id']);
            $line .= ' · '.__('last to :channel', ['channel' => $channel->name ?? '?']);
        }

        $tone = $counts[Shares::FAILED] > 0 ? 'warn' : ($counts[Shares::SENT] > 0 ? 'ok' : 'info');

        return new PanelView(mb_substr($line, 0, PanelView::MOST_STATUS), $tone);
    }

    public function settled(string $suggestionId, bool $used, EditorPanelContext $context): void
    {
        // Pollen's panel suggests no changes to the article; its captions wait in the Planner.
    }
}
