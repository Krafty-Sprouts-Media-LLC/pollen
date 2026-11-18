# Roadmap

What Pollen does in each version, and what is still to do. Each step of 1.0.0 is done when its test was seen to fail, then passes, it is tried on teme-dev, and it is committed and pushed. Nothing from a later version starts before 1.0.0 is usable.

Code written ahead of time for later versions is kept, untested, on the `parked/all-at-once` branch. It comes back one piece at a time, each with its own failing test.

## 1.0.0: usable sharing

Channels for **Facebook Pages, X, LinkedIn and Telegram**; posting on publish with a template per network; the editor panel to choose channels and edit the text; the log with re-send; notices when a channel needs connecting again.

| # | Step | State |
|---|---|---|
| 1 | Post on publish to a Telegram channel: queued jobs, retries, failed and held states | done: tried on teme-dev |
| 2 | Templates: Pollen's text, per network, per channel; variables; length fitted; link handling; UTM tags | done: tried on teme-dev |
| 3 | A Telegram channel connected with its typed token and checked with Telegram (`check-channel` job), held shares sent once it connects | done: 7 new tests, 36 in all; trial below |
| 4 | Pollen's area: channels (add, edit, switch off), the log of shares with re-send, overview figures | to do |
| 5 | Sign-in with the owner's own app: connect and callback addresses, renewal; Facebook Pages first, then X, then LinkedIn (profile and pages) | to do |
| 6 | The editor panel: choose channels for this item, edit the text per network, see what was shared and when, turn sharing off for the item | to do |
| 7 | Notices: a channel must be connected again, a channel keeps failing, a writer's share failed | to do |
| 8 | README, CHANGELOG, CONTRIBUTING, FEATURES; tried on teme-dev; REPORT | to do |

**1.0.0 is usable when** steps 1 to 5 and 8 are done: a site owner can connect Telegram, Facebook, X and LinkedIn channels, have new items shared, read the log, and re-send a failure. Steps 6 and 7 complete the release.

### Tried on teme-dev

Step 1, 2026-11-17, with `scripts/dev.ps1` (teme-dev's code, its own database `pollen_dev`, Pollen as the only plugin path, Teme reporting 1.0.0; teme-dev's `.env` untouched):

1. A test administrator was made with `teme:user:create` and set a password through the invitation page.
2. Plugins › Pollen › Enable showed the consent (read content; connect to api.telegram.org; background and scheduled work), asked for the password again, and enabled Pollen; its three tables were made.
3. Settings › Pollen drew both sections; a lock key and "Pause all sharing" were saved, and the settings history kept the key's value out.
4. One Telegram channel with a made-up token was added through Pollen's own store (the channel screens are step 4).
5. A post was written and published in Teme's editor; Pollen recorded it.
6. `teme:plugins:run-due-schedules --plugin=pollen`, then `queue:work`, ran Pollen's pass: one share was planned for the channel, two minutes after publishing. Paused, nothing was sent.

Step 2, 2026-11-18: Pollen was disabled, its tables dropped (`teme:plugins:remove-data`) and enabled again with consent through the console, as the new capability (reading writers' names) and the new column needed. Enabling listed the new line ("See the names of the people who write on this site"); Teme's settings form for Pollen now has ten fields, including "Text for each network" (a repeater). Not done on the trial site: seeing the share's text (sharing stays paused there, so no text is written) and a second publish through the editor, because the browser pane wouldn't hold a sign-in this time. The text itself is covered by 8 tests that read what Telegram was sent.

Step 3, 2026-11-18: on the trial site Pollen still loads as Enabled with the new `check-channel` job declared, and its scheduled pass runs through the queue worker. Not tried there: the check itself, because it calls Telegram, and a made-up token mustn't go to Telegram's real servers. The 4 tests cover it through the kit's fake. There is no screen to type a token into until step 4.

## 1.1: re-sharing older items

- [ ] Automations: re-share published items by type, category, tag, age and exclusions, at set times or intervals, without repeating an item until a set number of days or until all have been shared, taking turns across channels.
- [ ] Share on publish by rule: "when an item in category X is published, share to channels A and B with template T".

## 1.2: the planner

- [ ] A calendar of planned and sent shares; schedule a share of any published item for a date and time.
- [ ] A composer for posts that aren't items (an announcement with an image).

## 1.3: AI captions, images and workflows

- [ ] AI captions through the site's AI connection, left as suggestions for the writer.
- [ ] Image copies resized and cropped per network, and the watermark.
- [ ] Hooks for other plugins: `pollen:ShareRequests`, `pollen:ShareText`, `pollen:ShareSent`.
- [ ] First comments where the network allows them, and the link in the first comment.
- [ ] Reports: shares by channel and by item.

## Later: more networks, a group at a time

- [ ] Instagram (business), Threads, Pinterest, Bluesky, Mastodon (any server). The kit can't agree to a Mastodon server in a test (FINDINGS testing kit 5).
- [ ] Reddit, Discord (webhook), Google Business Profile, Tumblr.
- [ ] VK, Plurk, Blogger, another WordPress site, where APIs and terms allow.
- [ ] Import from WordPress: FS Poster's and Revive Social's settings.
- [ ] A "Check every channel" action.

## Can't yet

Needs a socket Teme doesn't have, or a network API that isn't open; see FINDINGS.md and FEATURES.md.

- Image uploads to X, Bluesky, Mastodon and LinkedIn, Flickr, TikTok photos (no binary or multipart outbound door).
- A shared connected-accounts socket (FINDINGS W1).
- The item's featured image and owner (W3, W3b).
- YouTube community posts, Xing, Medium, Truth Social, Odnoklassniki (no open API, or terms forbid).
