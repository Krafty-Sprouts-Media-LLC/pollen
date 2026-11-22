# Changelog

Each version's changes, newest first. Pollen follows semantic versioning. The 1.0.0 entry is being filled in as each step of [ROADMAP.md](ROADMAP.md) is finished; it is not released yet.

## 1.0.0 (in progress)

### Added

- Posting on publish: Pollen hears an item being published, or a scheduled item going live, plans one share for each channel that shares new items of its type, and sends each in a queued job after the wait set in Settings. Items unpublished, trashed or deleted before they go have their waiting shares cancelled.
- Retries and states: a busy network is tried up to three times, waiting 1 and then 5 minutes. A share is planned, sending, sent, failed, held or cancelled, with the network's own words. A channel whose sign-in is refused is marked "connect again" and its shares are held, then go when it connects.
- Templates: Pollen's text, a text for each network, and a text for each channel. Variables `{title}`, `{excerpt}`, `{content}`, `{link}`, `{author}`, `{hashtags}`, `{categories}`, `{tags}`, `{site}`, `{type}`. Text is cut at a word to each network's limit, counting a link as the network counts it and never cutting one. The link can be a card, in the text, in the first comment, or left out.
- UTM tags for shared links, from patterns for source, medium and campaign.
- Telegram: a channel is checked with Telegram by its typed bot token and chat (the `check-channel` job); a message is sent with a link preview.
- Settings › Pollen: the lock key, share text, text for each network, most hashtags, the wait after publishing, link tags, and a switch that pauses all sharing.
- Tokens are sealed with libsodium under the lock key and kept in Pollen's own table.
- `{author}` is the item's first credited person, or whoever published it when it credits nobody (FINDINGS W3b).
- Items whose writers turn sharing off aren't shared.
- Tests for each of these on Teme's testing kit, with outside networks faked.

### Still to do in 1.0.0

Pollen's screens (channels, the log with re-send), sign-in with the owner's own app for Facebook Pages, X and LinkedIn, the editor panel, and notices. See ROADMAP.md.

### Not in 1.0.0

Re-sharing older items (1.1), the planner and composer (1.2), AI captions, image copies, the watermark and hooks for other plugins (1.3), and more networks, a group at a time.
