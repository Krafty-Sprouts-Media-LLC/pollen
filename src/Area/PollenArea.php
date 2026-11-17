<?php

/**
 * Pollen: the data behind Pollen's own area in the admin.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Area;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use KraftySprouts\Pollen\Channels\Channel;
use KraftySprouts\Pollen\Compose\ItemFacts;
use KraftySprouts\Pollen\Compose\Template;
use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Networks\OAuthNetwork;
use KraftySprouts\Pollen\Networks\TokenNetwork;
use KraftySprouts\Pollen\Sharing\Planner;
use KraftySprouts\Pollen\Store\Shares;
use Teme\Contracts\Admin\AreaContext;
use Teme\Contracts\Admin\Chart;
use Teme\Contracts\Admin\Figure;
use Teme\Contracts\Admin\PluginArea;
use Teme\Contracts\Admin\Row;
use Teme\Contracts\Admin\RowPage;
use Teme\Contracts\Admin\Saved;
use Teme\Contracts\Admin\TableQuery;
use Teme\Contracts\Content\ItemQuery;
use Teme\Contracts\Content\ReadRefused;
use Teme\Contracts\Content\WriteRefused;
use Teme\Contracts\Jobs\QueueOptions;
use Teme\Contracts\Lists\Cell;
use Throwable;

/**
 * Hands core what Pollen's area shows, and stores what its forms save: the
 * overview's figures and charts, the planner and its shares, the channels
 * and their sign-ins, the automations, and the reports. Writers see their
 * own shares; the people who manage Pollen see everything and may change
 * channels and automations.
 *
 * @since 1.0.0
 */
final readonly class PollenArea implements PluginArea
{
    /** The networks whose channels sign in through a server the site names. */
    private const ON_A_SERVER = ['mastodon', 'wordpress'];

    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    public function figures(string $page, string $block, AreaContext $context): array
    {
        $this->remember($context);
        $since = now()->subDays(30);
        $health = $this->doors->channels->health();

        return [
            'sent' => Figure::number($this->scoped($context)->where('status', Shares::SENT)->where('sent_at', '>=', $since)->count()),
            'failed' => Figure::number($this->scoped($context)->where('status', Shares::FAILED)->where('updated_at', '>=', $since)->count()),
            'waiting' => Figure::number($this->scoped($context)->whereIn('status', [Shares::PLANNED, Shares::SENDING])->count()),
            'attention' => Figure::number($health['attention']),
        ];
    }

    public function chart(string $page, string $block, AreaContext $context): Chart
    {
        $zone = $this->zone($context);

        return match ($page.'/'.$block) {
            'overview/daily' => $this->daily($context, $zone),
            'planner/ahead' => $this->ahead($context, $zone),
            'reports/by-channel' => $this->byChannel($context),
            'reports/by-item' => $this->byItem($context),
            default => new Chart([], []),
        };
    }

    public function rows(string $record, TableQuery $query, AreaContext $context): RowPage
    {
        $this->remember($context);

        return match ($record) {
            'channel' => $this->channelRows($query, $context),
            'share' => $this->shareRows($query, $context),
            'automation' => $this->automationRows($query, $context),
            default => new RowPage([], 0),
        };
    }

    public function values(string $record, string $id, AreaContext $context): ?array
    {
        $this->remember($context);

        if ($record === 'channel') {
            $channel = $this->doors->channels->find($id);

            return $channel === null ? null : [
                'name' => $channel->name,
                'network' => $channel->network,
                'account' => $channel->account,
                'labels' => implode(', ', $channel->labels),
                'auto_types' => $channel->autoTypes,
                'server' => $channel->server,
                'handle' => $channel->handle,
                'token' => '',
                'template' => $channel->option('template'),
                'first_comment' => $channel->option('first_comment'),
                'link' => $channel->option('link', 'card'),
                'image' => $channel->option('image', 'default'),
                'extra' => $channel->option('extra'),
                'access' => $channel->access,
                'people' => implode(', ', $channel->people),
            ];
        }

        if ($record === 'share') {
            $share = $this->doors->shares->find($id);

            if ($share === null || ! $this->mayTouchShare($share, $context)) {
                return null;
            }

            return [
                'item' => (string) ($share['item_url'] !== '' ? $share['item_url'] : ($share['item_id'] ?? '')),
                'message' => (string) ($share['message'] ?? $share['text'] ?? ''),
                'image' => (string) $share['image_url'],
                'first_comment' => (string) $share['first_comment'],
                'channels' => $this->doors->channels->find((string) $share['channel_id'])->name ?? '',
                'send_at' => (new DateTimeImmutable((string) $share['send_at']))->setTimezone($this->zone($context))->format('Y-m-d\TH:i'),
            ];
        }

        if ($record === 'automation') {
            $automation = $this->doors->automations->find($id);

            if ($automation === null) {
                return null;
            }

            return array_filter(['name' => $automation->name, 'kind' => $automation->kind, ...$automation->settings], fn (string|int $key): bool => ! str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    public function save(string $record, ?string $id, array $values, AreaContext $context): Saved
    {
        $this->remember($context);

        return match ($record) {
            'channel' => $this->saveChannel($id, $values, $context),
            'share' => $this->saveShare($id, $values, $context),
            'automation' => $this->saveAutomation($id, $values, $context),
            default => throw new WriteRefused(__('Pollen keeps no record of that kind.')),
        };
    }

    public function act(string $record, string $action, array $ids, AreaContext $context): Saved
    {
        $this->remember($context);
        $ids = array_values(array_map('strval', $ids));

        if ($record === 'share') {
            return $this->actOnShares($action, $ids, $context);
        }

        $this->mustManage($context);

        $changed = match ($record.'/'.$action) {
            'channel/enable' => $this->doors->channels->setActive($ids, true),
            'channel/disable' => $this->doors->channels->setActive($ids, false),
            'channel/delete' => $this->doors->channels->delete($ids),
            'channel/duplicate' => $this->duplicateChannels($ids, $context),
            'automation/enable' => $this->doors->automations->setActive($ids, true),
            'automation/disable' => $this->doors->automations->setActive($ids, false),
            'automation/delete' => $this->doors->automations->delete($ids),
            'automation/duplicate' => $this->duplicateAutomations($ids, $context),
            default => throw new WriteRefused(__('Pollen can’t do that here.')),
        };

        return new Saved($ids[0] ?? '', $changed > 0);
    }

    /**
     * Stores a channel, refusing what can't work, and starts its check or gives it a connect address.
     *
     * @param  array<string, mixed>  $values
     */
    private function saveChannel(?string $id, array $values, AreaContext $context): Saved
    {
        $this->mustManage($context);
        $network = $this->doors->networks->find((string) ($values['network'] ?? ''));

        if ($network === null) {
            throw new WriteRefused(__('Choose a network Pollen knows.'));
        }

        $existing = $id === null ? null : $this->doors->channels->find($id);

        if ($id !== null && $existing === null) {
            throw new WriteRefused(__('That channel no longer exists.'));
        }

        $server = trim((string) ($values['server'] ?? ''));
        $host = strtolower((string) parse_url($server, PHP_URL_HOST));

        if (in_array($network->key(), self::ON_A_SERVER, true) && $host === '') {
            throw new WriteRefused(__('Give the server’s address, such as https://mastodon.social.'));
        }

        if ($host !== '' && ! $this->doors->settings->serverListed($host)) {
            throw new WriteRefused(__('Add :server under Servers in Settings › Pollen first, so Teme lets Pollen reach it.', ['server' => 'https://'.$host]));
        }

        $template = (string) ($values['template'] ?? '');

        if (($unknown = Template::unknown($template)) !== []) {
            throw new WriteRefused(__('The text names {:names}, which Pollen doesn’t know.', ['names' => implode('}, {', $unknown)]));
        }

        $token = trim((string) ($values['token'] ?? ''));
        $vault = $this->doors->settings->vault();

        if ($token !== '' && ! $vault->ready()) {
            throw new WriteRefused(__('Set Pollen’s lock key in Settings › Pollen before giving a channel its token.'));
        }

        $row = [
            'name' => trim((string) $values['name']),
            'network' => $network->key(),
            'account' => trim((string) ($values['account'] ?? '')),
            'labels' => self::words((string) ($values['labels'] ?? '')),
            'auto_types' => array_values(array_filter(array_map('strval', is_array($values['auto_types'] ?? null) ? $values['auto_types'] : []))),
            'server' => $server,
            'handle' => trim((string) ($values['handle'] ?? '')),
            'access' => in_array($values['access'] ?? '', ['sharers', 'managers', 'listed'], true) ? (string) $values['access'] : 'sharers',
            'people' => $this->peopleIds((string) ($values['people'] ?? '')),
            'options' => [
                ...($existing->options ?? []),
                'template' => $template,
                'first_comment' => (string) ($values['first_comment'] ?? ''),
                'link' => (string) ($values['link'] ?? 'card'),
                'image' => (string) ($values['image'] ?? 'default'),
                'extra' => trim((string) ($values['extra'] ?? '')),
            ],
        ];

        if ($token !== '') {
            $row['sealed'] = $vault->seal($network->key() === 'bluesky' || $network->key() === 'wordpress' ? ['password' => $token] : ['token' => $token]);
            $row['status'] = Channel::NEW;
        }

        $accountChanged = $existing !== null && $existing->account !== $row['account'];

        if ($accountChanged && $existing->status === Channel::CHOOSE) {
            $row = $this->chooseAccount($existing, $row, $vault);
        } elseif ($accountChanged || ($existing !== null && $host !== $existing->serverHost())) {
            $row['status'] = Channel::NEW;
        }

        $saved = $this->doors->channels->save($id, $row, $context->personId);

        if ($network instanceof OAuthNetwork && ($id === null || ($row['status'] ?? $existing?->status) !== Channel::CONNECTED)) {
            $this->doors->channels->newConnectKey($saved);
        }

        if ($network instanceof TokenNetwork && ($token !== '' || $accountChanged) && $this->doors->jobs !== null) {
            // Checking the token reaches the network, which a save's two seconds can't wait for.
            $this->doors->jobs->queue('check-channels', ['channel' => $saved], __('Pollen: check “:name”', ['name' => $row['name']]), new QueueOptions(uniqueKey: 'check-'.$saved));
        }

        return new Saved($saved);
    }

    /**
     * Returns the channel's row with the account chosen among those its sign-in reaches.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function chooseAccount(Channel $channel, array $row, \KraftySprouts\Pollen\Store\Vault $vault): array
    {
        $choices = is_array($channel->options['choices'] ?? null) ? $channel->options['choices'] : [];
        $chosen = \KraftySprouts\Pollen\Networks\Connected::pick($choices, (string) $row['account']);

        if ($chosen === null) {
            throw new WriteRefused(__('Choose one of the accounts this sign-in reaches: :names.', ['names' => implode(', ', array_map(fn (array $c): string => $c['name'].' ('.$c['id'].')', $choices))]));
        }

        $credentials = $vault->open($channel->sealed);

        foreach (is_array($credentials['pages'] ?? null) ? $credentials['pages'] : [] as $page) {
            if (($page['id'] ?? null) === $chosen['id'] && $channel->network === 'facebook') {
                $credentials['token'] = $page['token'];
            }
        }

        unset($row['options']['choices']);

        return [...$row, 'account' => $chosen['id'], 'account_name' => $chosen['name'], 'status' => Channel::CONNECTED, 'sealed' => $vault->seal($credentials)];
    }

    /**
     * Plans a new share, one a channel, or changes one that hasn't gone.
     *
     * @param  array<string, mixed>  $values
     */
    private function saveShare(?string $id, array $values, AreaContext $context): Saved
    {
        $this->mustShare($context);
        $zone = $this->zone($context);
        $when = trim((string) ($values['send_at'] ?? ''));
        $at = $when === '' ? now()->toDateTimeImmutable() : new DateTimeImmutable($when, $zone);
        $message = (string) ($values['message'] ?? '');

        if (($unknown = Template::unknown($message)) !== []) {
            throw new WriteRefused(__('The text names {:names}, which Pollen doesn’t know.', ['names' => implode('}, {', $unknown)]));
        }

        if ($id !== null) {
            $share = $this->doors->shares->find($id);

            if ($share === null || ! $this->mayTouchShare($share, $context)) {
                throw new WriteRefused(__('That share no longer exists.'));
            }

            if (! in_array($share['status'], [Shares::PLANNED, Shares::SUGGESTED, Shares::HELD], true)) {
                throw new WriteRefused(__('This share already went. Duplicate it to send it again.'));
            }

            DB::table('pollen_shares')->where('id', $id)->update([
                'message' => trim($message) !== '' ? $message : null,
                'image_url' => trim((string) ($values['image'] ?? '')),
                'first_comment' => (string) ($values['first_comment'] ?? ''),
                'send_at' => $at,
                'updated_at' => now(),
            ]);

            return new Saved($id);
        }

        $item = $this->item(trim((string) ($values['item'] ?? '')), $context);

        if ($item === null && trim($message) === '') {
            throw new WriteRefused(__('Name an item to share, or write a message of your own.'));
        }

        $unknownChannels = [];
        $refused = [];
        $ids = (new Planner($this->doors))->byPerson($context->personId, $item, (string) ($values['channels'] ?? ''), $message, $at, trim((string) ($values['image'] ?? '')), (string) ($values['first_comment'] ?? ''), $item === null ? 'composer' : 'planner', $unknownChannels, $refused);

        if ($unknownChannels !== []) {
            throw new WriteRefused(__('No channel or label is named :names.', ['names' => implode(', ', $unknownChannels)]));
        }

        if ($ids === []) {
            throw new WriteRefused($refused !== [] ? __('You may not post to :names.', ['names' => implode(', ', $refused)]) : __('No channel is switched on.'));
        }

        return new Saved($ids[0]);
    }

    /**
     * Stores an automation, refusing channels or variables Pollen doesn't know.
     *
     * @param  array<string, mixed>  $values
     */
    private function saveAutomation(?string $id, array $values, AreaContext $context): Saved
    {
        $this->mustManage($context);
        $unknown = [];
        $this->doors->channels->named((string) ($values['channels'] ?? ''), $unknown);

        if ($unknown !== []) {
            throw new WriteRefused(__('No channel or label is named :names.', ['names' => implode(', ', $unknown)]));
        }

        if (($bad = Template::unknown((string) ($values['template'] ?? ''))) !== []) {
            throw new WriteRefused(__('The text names {:names}, which Pollen doesn’t know.', ['names' => implode('}, {', $bad)]));
        }

        $kind = ($values['kind'] ?? 'publish') === 'reshare' ? 'reshare' : 'publish';
        $existing = $id === null ? null : $this->doors->automations->find($id);
        $settings = array_diff_key($values, ['name' => true, 'kind' => true]);
        $settings['_zone'] = $context->timezone;

        if ($existing !== null && isset($existing->settings['_round'])) {
            $settings['_round'] = $existing->settings['_round'];
        }

        return new Saved($this->doors->automations->save($id, trim((string) ($values['name'] ?? '')), $kind, $settings, $context->personId));
    }

    /**
     * Runs a row action on shares: send again, plan or hold, or delete.
     *
     * @param  list<string>  $ids
     */
    private function actOnShares(string $action, array $ids, AreaContext $context): Saved
    {
        $this->mustShare($context);
        $changed = 0;
        $first = $ids[0] ?? '';

        foreach ($ids as $id) {
            $share = $this->doors->shares->find($id);

            if ($share === null || ! $this->mayTouchShare($share, $context)) {
                continue;
            }

            if ($action === 'duplicate') {
                $copy = $this->doors->shares->plan(array_filter([
                    'channel_id' => (string) $share['channel_id'],
                    'origin' => 'again',
                    'item_id' => $share['item_id'],
                    'item_title' => (string) $share['item_title'],
                    'item_url' => (string) $share['item_url'],
                    'automation_id' => $share['automation_id'],
                    'message' => $share['message'],
                    'image_url' => (string) $share['image_url'],
                    'first_comment' => (string) $share['first_comment'],
                    'requested_by' => $context->personId,
                ], fn (mixed $v): bool => $v !== null), now());
                $first = $copy ?? $first;
                $changed++;
            } elseif ($action === 'enable' && in_array($share['status'], [Shares::SUGGESTED, Shares::HELD], true)) {
                $at = max(strtotime((string) $share['send_at']), time());
                $changed += DB::table('pollen_shares')->where('id', $id)->update(['status' => Shares::PLANNED, 'send_at' => new DateTimeImmutable('@'.$at), 'reply' => '', 'updated_at' => now()]);
            } elseif ($action === 'disable' && $share['status'] === Shares::PLANNED) {
                $this->doors->shares->stopped($id, Shares::HELD, __('Held by a person.'));
                $changed++;
            } elseif ($action === 'delete' && $share['status'] !== Shares::SENDING) {
                $changed += DB::table('pollen_shares')->where('id', $id)->delete();
            }
        }

        return new Saved($first, $changed > 0);
    }

    /**
     * Returns the channel rows: search by name, sort by name or network.
     */
    private function channelRows(TableQuery $query, AreaContext $context): RowPage
    {
        $builder = DB::table('pollen_channels');

        if ($query->search !== '') {
            $builder->where(fn ($q) => $q->where('name', 'ilike', '%'.$query->search.'%')->orWhere('network', 'ilike', '%'.$query->search.'%')->orWhere('labels', 'ilike', '%'.$query->search.'%'));
        }

        $total = (clone $builder)->count();
        $builder->orderBy(in_array($query->sort, ['name', 'network'], true) ? $query->sort : 'name', $query->direction === 'desc' ? 'desc' : 'asc');
        $sent = DB::table('pollen_shares')->where('status', Shares::SENT)->groupBy('channel_id')->selectRaw('channel_id, count(*) as n')->pluck('n', 'channel_id')->all();
        $manager = in_array('manage', $context->permissions, true);
        $rows = [];

        foreach ($builder->forPage(max(1, $query->page), max(1, $query->perPage))->get() as $row) {
            $channel = \KraftySprouts\Pollen\Store\Channels::channel((array) $row);
            $network = $this->doors->networks->find($channel->network);

            $rows[] = new Row($channel->id, [
                'name' => Cell::text($channel->name.($channel->labels !== [] ? ' · '.implode(', ', $channel->labels) : '')),
                'network' => Cell::text(($network?->label() ?? $channel->network).($channel->accountName !== '' ? ' · '.$channel->accountName : '')),
                'status' => self::channelBadge($channel),
                'signin' => Cell::text($manager ? $this->signIn($channel) : ''),
                'sent' => Cell::number((int) ($sent[$channel->id] ?? 0)),
            ], array_values(array_filter([$channel->active ? 'enable' : 'disable', $manager ? null : 'edit', $manager ? null : 'delete', $manager ? null : 'duplicate'])));
        }

        return new RowPage($rows, $total);
    }

    /**
     * Returns the share rows, a writer's own unless they manage Pollen.
     */
    private function shareRows(TableQuery $query, AreaContext $context): RowPage
    {
        $builder = $this->scoped($context);
        $status = $query->filters['status'] ?? null;
        $status = is_array($status) ? ($status[0] ?? null) : $status;

        if (is_string($status) && $status !== '') {
            $builder->whereIn('status', $status === 'planned' ? [Shares::PLANNED, Shares::SENDING] : [$status]);
        } else {
            $builder->whereNotIn('status', [Shares::CANCELLED]);
        }

        if ($query->search !== '') {
            $builder->where(fn ($q) => $q->where('item_title', 'ilike', '%'.$query->search.'%')->orWhere('text', 'ilike', '%'.$query->search.'%')->orWhere('message', 'ilike', '%'.$query->search.'%'));
        }

        $total = (clone $builder)->count();
        $column = match ($query->sort) {
            'channel' => 'channel_id',
            'status' => 'status',
            default => 'send_at',
        };
        $builder->orderBy($column, $query->direction === 'asc' ? 'asc' : 'desc');
        $names = DB::table('pollen_channels')->pluck('name', 'id')->all();
        $zone = $this->zone($context);
        $rows = [];

        foreach ($builder->forPage(max(1, $query->page), max(1, $query->perPage))->get() as $row) {
            $share = (array) $row;
            $when = (new DateTimeImmutable((string) ($share['sent_at'] ?? $share['send_at'])))->setTimezone($zone)->format('Y-m-d H:i');
            $what = $share['item_title'] !== '' ? (string) $share['item_title'] : mb_substr((string) ($share['message'] ?? $share['text'] ?? ''), 0, 80);
            $reply = (string) $share['reply'] === 'awaiting-publication' ? __('Waits for the item to be published.') : (string) $share['reply'];

            if ($share['remote_url'] !== '') {
                $reply = trim($reply.' '.$share['remote_url']);
            }

            $rows[] = new Row((string) $share['id'], [
                'when' => Cell::text($when),
                'item' => Cell::text($what),
                'channel' => Cell::text((string) ($names[$share['channel_id']] ?? __('Deleted channel'))),
                'status' => self::shareBadge((string) $share['status']),
                'reply' => Cell::text($reply),
            ], self::shareUnavailable((string) $share['status']));
        }

        return new RowPage($rows, $total);
    }

    /**
     * Returns the automation rows.
     */
    private function automationRows(TableQuery $query, AreaContext $context): RowPage
    {
        if (! in_array('manage', $context->permissions, true)) {
            return new RowPage([], 0);
        }

        $builder = DB::table('pollen_automations');

        if ($query->search !== '') {
            $builder->where('name', 'ilike', '%'.$query->search.'%');
        }

        $total = (clone $builder)->count();
        $builder->orderBy('name', $query->direction === 'desc' ? 'desc' : 'asc');
        $sent = DB::table('pollen_shares')->where('status', Shares::SENT)->whereNotNull('automation_id')->groupBy('automation_id')->selectRaw('automation_id, count(*) as n')->pluck('n', 'automation_id')->all();
        $zone = $this->zone($context);
        $rows = [];

        foreach ($builder->forPage(max(1, $query->page), max(1, $query->perPage))->get() as $row) {
            $automation = \KraftySprouts\Pollen\Automations\Automation::fromRow((array) $row);
            $next = $automation->kind === 'publish' ? __('On publish') : ($automation->nextRunAt === null ? __('Within a minute') : (new DateTimeImmutable('@'.$automation->nextRunAt))->setTimezone($zone)->format('Y-m-d H:i'));

            $rows[] = new Row($automation->id, [
                'name' => Cell::text($automation->name),
                'kind' => Cell::text(($automation->kind === 'publish' ? __('Shares on publish to :channels', ['channels' => $automation->text('channels')]) : __('Re-shares to :channels', ['channels' => $automation->text('channels')])).((string) $row->last_note !== '' ? ' · '.$row->last_note : '')),
                'status' => $automation->active ? Cell::badge(__('On'), 'ok') : Cell::badge(__('Off'), 'neutral'),
                'next' => Cell::text($automation->active ? $next : '—'),
                'sent' => Cell::number((int) ($sent[$automation->id] ?? 0)),
            ], [$automation->active ? 'enable' : 'disable']);
        }

        return new RowPage($rows, $total);
    }

    /**
     * Returns what a channel's Sign-in column says: how it is connected, or the address that connects it.
     */
    private function signIn(Channel $channel): string
    {
        $network = $this->doors->networks->find($channel->network);

        if ($channel->status === Channel::CHOOSE) {
            $choices = is_array($channel->options['choices'] ?? null) ? $channel->options['choices'] : [];

            return __('Type one of these in Account, then save: :names', ['names' => implode(', ', array_map(fn (array $c): string => $c['name'].' ('.$c['id'].')', array_slice($choices, 0, 10)))]);
        }

        if ($channel->status === Channel::CONNECTED) {
            return __('Connected as :account', ['account' => $channel->accountName !== '' ? $channel->accountName : $channel->account]);
        }

        if ($network instanceof OAuthNetwork) {
            $app = $this->doors->settings->app($network->app());

            if (! $app->ready()) {
                return __('Add your :network app keys in Settings › Pollen first.', ['network' => $network->label()]);
            }

            if (! $this->doors->settings->vault()->ready()) {
                return __('Set Pollen’s lock key in Settings › Pollen first.');
            }

            $key = (string) DB::table('pollen_channels')->where('id', $channel->id)->value('connect_key');

            if ($key === '') {
                $key = $this->doors->channels->newConnectKey($channel->id);
            }

            return trim(($channel->lastError !== '' ? $channel->lastError.' ' : '').__('Open :address to connect.', ['address' => $this->doors->connectUrl($key)]));
        }

        return $channel->lastError !== '' ? $channel->lastError : __('Give its token and save; Pollen checks it within a minute.');
    }

    /**
     * Returns the item a share names, by ID or address, read for the person; null when none is named.
     */
    private function item(string $named, AreaContext $context): ?ItemFacts
    {
        if ($named === '' || $this->doors->content === null) {
            return null;
        }

        $id = preg_match('/^[0-9a-z]{26}$/', strtolower($named)) === 1 ? strtolower($named) : $this->itemByAddress($named);

        if ($id === null) {
            throw new WriteRefused(__('No published item has that address.'));
        }

        try {
            $facts = ItemFacts::read($this->doors->content, $this->doors->peopleReader, $id, $context->personId);
        } catch (ReadRefused $refused) {
            throw new WriteRefused($refused->getMessage());
        }

        if ($facts->status !== 'published') {
            throw new WriteRefused(__('That item isn’t published yet. Plan its shares from its editor instead.'));
        }

        return $facts;
    }

    /**
     * Returns the ID of the published item at this address, looking through the newest two thousand.
     */
    private function itemByAddress(string $address): ?string
    {
        $wanted = rtrim(strtolower(strtok($address, '?#') ?: $address), '/');
        $after = null;

        for ($page = 0; $page < 10; $page++) {
            $found = $this->doors->content?->published(new ItemQuery(after: $after, limit: 200));

            if ($found === null) {
                return null;
            }

            foreach ($found->items as $item) {
                if ($item->url !== null && rtrim(strtolower($item->url), '/') === $wanted) {
                    return $item->id;
                }
            }

            if (($after = $found->next) === null) {
                return null;
            }
        }

        return null;
    }

    /**
     * Returns the account IDs named by display name or number.
     *
     * @return list<string>
     */
    private function peopleIds(string $named): array
    {
        $words = self::words($named);

        if ($words === []) {
            return [];
        }

        $ids = [];
        $missing = [];
        $writers = [];

        if ($this->doors->peopleReader !== null) {
            $after = null;

            for ($page = 0; $page < 5; $page++) {
                $found = $this->doors->peopleReader->writers($after, 200);
                $writers = [...$writers, ...$found->people];

                if (($after = $found->next) === null) {
                    break;
                }
            }
        }

        foreach ($words as $word) {
            $hit = null;

            foreach ($writers as $person) {
                if ($person->id === $word || mb_strtolower($person->name) === mb_strtolower($word)) {
                    $hit = $person->id;
                }
            }

            $hit === null ? $missing[] = $word : $ids[] = $hit;
        }

        if ($missing !== []) {
            throw new WriteRefused(__('Nobody who writes here is named :names.', ['names' => implode(', ', $missing)]));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Returns the share query a person may see: everyone's for managers, their own for others.
     */
    private function scoped(AreaContext $context): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('pollen_shares');

        return in_array('manage', $context->permissions, true) ? $query : $query->where('requested_by', $context->personId);
    }

    /**
     * Returns the chart of shares sent and failed each day for 30 days.
     */
    private function daily(AreaContext $context, DateTimeZone $zone): Chart
    {
        $labels = [];
        $sent = [];
        $failed = [];
        $start = now()->setTimezone($zone)->startOfDay()->subDays(29);

        $rows = $this->scoped($context)->whereIn('status', [Shares::SENT, Shares::FAILED])->where('updated_at', '>=', $start)->get(['status', 'sent_at', 'updated_at']);

        for ($day = 0; $day < 30; $day++) {
            $labels[] = $start->copy()->addDays($day)->format('Y-m-d');
        }

        $counts = array_fill_keys($labels, [0, 0]);

        foreach ($rows as $row) {
            $date = (new DateTimeImmutable((string) ($row->sent_at ?? $row->updated_at)))->setTimezone($zone)->format('Y-m-d');

            if (isset($counts[$date])) {
                $counts[$date][$row->status === Shares::SENT ? 0 : 1]++;
            }
        }

        foreach ($counts as [$s, $f]) {
            $sent[] = $s;
            $failed[] = $f;
        }

        return new Chart($labels, ['sent' => $sent, 'failed' => $failed]);
    }

    /**
     * Returns the chart of shares planned each day for the next 30 days: Pollen's calendar.
     */
    private function ahead(AreaContext $context, DateTimeZone $zone): Chart
    {
        $start = now()->setTimezone($zone)->startOfDay();
        $labels = [];

        for ($day = 0; $day < 30; $day++) {
            $labels[] = $start->copy()->addDays($day)->format('Y-m-d');
        }

        $counts = array_fill_keys($labels, 0);

        foreach ($this->scoped($context)->whereIn('status', [Shares::PLANNED, Shares::SENDING])->where('send_at', '<', $start->copy()->addDays(30))->pluck('send_at') as $at) {
            $date = max($labels[0], (new DateTimeImmutable((string) $at))->setTimezone($zone)->format('Y-m-d'));

            if (isset($counts[$date])) {
                $counts[$date]++;
            }
        }

        return new Chart($labels, ['planned' => array_values($counts)]);
    }

    /**
     * Returns shares sent and failed by channel over 90 days.
     */
    private function byChannel(AreaContext $context): Chart
    {
        $names = DB::table('pollen_channels')->orderBy('name')->pluck('name', 'id')->all();
        $counts = $this->scoped($context)->where('updated_at', '>=', now()->subDays(90))->whereIn('status', [Shares::SENT, Shares::FAILED])->groupBy('channel_id', 'status')->selectRaw('channel_id, status, count(*) as n')->get();
        $sent = array_fill_keys(array_keys($names), 0);
        $failed = $sent;

        foreach ($counts as $row) {
            if (isset($sent[$row->channel_id])) {
                $row->status === Shares::SENT ? $sent[$row->channel_id] = (int) $row->n : $failed[$row->channel_id] = (int) $row->n;
            }
        }

        return new Chart(array_values(array_map(fn (string $n): string => mb_substr($n, 0, 40), $names)), ['sent' => array_values($sent), 'failed' => array_values($failed)]);
    }

    /**
     * Returns the fifteen items shared most over 90 days.
     */
    private function byItem(AreaContext $context): Chart
    {
        $rows = $this->scoped($context)->where('status', Shares::SENT)->where('sent_at', '>=', now()->subDays(90))->whereNotNull('item_id')
            ->groupBy('item_id', 'item_title')->selectRaw('item_id, item_title, count(*) as n')->orderByDesc('n')->limit(15)->get();

        return new Chart(
            array_map(fn (object $r): string => mb_substr((string) ($r->item_title !== '' ? $r->item_title : $r->item_id), 0, 40), $rows->all()),
            ['sent' => array_map(fn (object $r): int => (int) $r->n, $rows->all())],
        );
    }

    /**
     * Copies channels without their sign-ins.
     *
     * @param  list<string>  $ids
     */
    private function duplicateChannels(array $ids, AreaContext $context): int
    {
        $made = 0;

        foreach ($ids as $id) {
            $channel = $this->doors->channels->find($id);

            if ($channel !== null) {
                $options = $channel->options;
                unset($options['choices']);
                $this->doors->channels->save(null, [
                    'name' => mb_substr(__(':name (copy)', ['name' => $channel->name]), 0, 80),
                    'network' => $channel->network,
                    'account' => $channel->account,
                    'labels' => $channel->labels,
                    'auto_types' => [],
                    'server' => $channel->server,
                    'handle' => $channel->handle,
                    'access' => $channel->access,
                    'people' => $channel->people,
                    'options' => $options,
                    'active' => false,
                ], $context->personId);
                $made++;
            }
        }

        return $made;
    }

    /**
     * Copies automations, switched off.
     *
     * @param  list<string>  $ids
     */
    private function duplicateAutomations(array $ids, AreaContext $context): int
    {
        $made = 0;

        foreach ($ids as $id) {
            $automation = $this->doors->automations->find($id);

            if ($automation !== null) {
                $settings = $automation->settings;
                unset($settings['_round']);
                $copy = $this->doors->automations->save(null, mb_substr(__(':name (copy)', ['name' => $automation->name]), 0, 80), $automation->kind, $settings, $context->personId);
                $this->doors->automations->setActive([$copy], false);
                $made++;
            }
        }

        return $made;
    }

    /**
     * Returns whether the person may see and change this share.
     *
     * @param  array<string, mixed>  $share
     */
    private function mayTouchShare(array $share, AreaContext $context): bool
    {
        return in_array('manage', $context->permissions, true) || $share['requested_by'] === $context->personId;
    }

    /**
     * Refuses a write by someone who doesn't manage Pollen.
     */
    private function mustManage(AreaContext $context): void
    {
        if (! in_array('manage', $context->permissions, true)) {
            throw new WriteRefused(__('Only people who manage Pollen can change channels and automations.'));
        }
    }

    /**
     * Refuses a write by someone who may not share with Pollen.
     */
    private function mustShare(AreaContext $context): void
    {
        if (! array_intersect(['share', 'manage'], $context->permissions)) {
            throw new WriteRefused(__('You may not share with Pollen.'));
        }
    }

    /**
     * Records which of Pollen's permissions the person holds, and their time zone, for the jobs they start.
     */
    private function remember(AreaContext $context): void
    {
        try {
            $this->doors->people->saw($context->personId, $context->permissions, $context->timezone);
        } catch (Throwable) {
            // Remembering is a help, never a reason to fail the page.
        }
    }

    /**
     * Returns the person's time zone.
     */
    private function zone(AreaContext $context): DateTimeZone
    {
        try {
            return new DateTimeZone($context->timezone !== '' ? $context->timezone : 'UTC');
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Returns the badge for a channel's state.
     */
    private static function channelBadge(Channel $channel): Cell
    {
        if (! $channel->active) {
            return Cell::badge(__('Off'), 'neutral');
        }

        return match ($channel->status) {
            Channel::CONNECTED => $channel->failures > 0 ? Cell::badge(__('Failing'), 'warn') : Cell::badge(__('Connected'), 'ok'),
            Channel::CHOOSE => Cell::badge(__('Choose account'), 'warn'),
            Channel::RECONNECT => Cell::badge(__('Connect again'), 'danger'),
            default => Cell::badge(__('Not connected'), 'warn'),
        };
    }

    /**
     * Returns the badge for a share's state.
     */
    private static function shareBadge(string $status): Cell
    {
        return match ($status) {
            Shares::SENT => Cell::badge(__('Sent'), 'ok'),
            Shares::FAILED => Cell::badge(__('Failed'), 'danger'),
            Shares::PLANNED => Cell::badge(__('Planned'), 'info'),
            Shares::SENDING => Cell::badge(__('Sending'), 'info'),
            Shares::SUGGESTED => Cell::badge(__('Suggested'), 'warn'),
            Shares::HELD => Cell::badge(__('Held'), 'warn'),
            default => Cell::badge(__('Cancelled'), 'neutral'),
        };
    }

    /**
     * Returns the row actions a share in this state doesn't offer.
     *
     * @return list<string>
     */
    private static function shareUnavailable(string $status): array
    {
        return match ($status) {
            Shares::PLANNED => ['enable'],
            Shares::SUGGESTED, Shares::HELD => ['disable'],
            Shares::SENDING => ['edit', 'enable', 'disable', 'delete'],
            default => ['edit', 'enable', 'disable'],
        };
    }

    /**
     * Returns the words of a comma-separated list, trimmed, without empties.
     *
     * @return list<string>
     */
    private static function words(string $list): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $list)), fn (string $w): bool => $w !== ''));
    }
}
