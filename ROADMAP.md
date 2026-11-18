# Roadmap

What Pollen does in each version, and what is still to do. Each step of 1.0.0 is done when its test was seen to fail, then passes, and it is committed. Nothing from a later version starts before 1.0.0 is usable: enabled on a real Teme site, a channel connected, items shared, and the README telling a site owner how.

Code written ahead of time for later versions is kept, untested, on the `parked/all-at-once` branch. It comes back one piece at a time, each with its own failing test.

## 1.0.0: usable sharing

| # | Step | State |
|---|---|---|
| 1 | Post on publish to one Telegram channel: queued jobs, retries, failed and held states | done: 21 tests pass; tried on teme-dev (below) |
| 2 | Templates: Pollen's text, per network, per channel; variables; length fitted; link handling; UTM tags | to do |
| 3 | Channels connected with a typed token: Mastodon, Telegram, Bluesky; checked when saved | to do |
| 4 | Pollen's area: channels (add, edit, switch off), the planner and log, overview figures | to do |
| 5 | Sign-in with the owner's app (OAuth): the connect and callback addresses, renewal | to do |
| 6 | X, Facebook Pages, Instagram, Threads, LinkedIn, Pinterest, one at a time | to do |
| 7 | Editor panel: plan shares for this item, turn sharing off for it | to do |
| 8 | Automations: share on publish by type, category and tag, with a template | to do |
| 9 | Re-sharing older items on a timetable (age, exclusions, no repeats, taking turns) | to do |
| 10 | Notices: a channel must be connected again, a channel keeps failing, a writer's share failed | to do |
| 11 | README, CHANGELOG, CONTRIBUTING, FEATURES; tried on teme-dev; REPORT | to do |

### Tried on teme-dev

Step 1, on 2026-11-17, with `scripts/dev.ps1` (teme-dev's code, its own database `pollen_dev`, Pollen as the only plugin path, Teme reporting 1.0.0; teme-dev's `.env` untouched):

1. A test administrator was made with `teme:user:create` and set a password through the invitation page.
2. Plugins › Pollen › Enable showed the consent (read content; connect to api.telegram.org; background and scheduled work), asked for the password again, and enabled Pollen; its three tables were made.
3. Settings › Pollen drew both sections; a lock key and "Pause all sharing" were saved, and the settings history kept the key's value out.
4. One Telegram channel with a made-up token was added through Pollen's own store (the channel screens are step 4).
5. A post was written and published in Teme's editor; Pollen recorded it.
6. `teme:plugins:run-due-schedules --plugin=pollen`, then `queue:work`, ran Pollen's pass: one share was planned for the channel, two minutes after publishing. Paused, nothing was sent.

**1.0.0 is usable when** steps 1 to 5 and 11 are done: a site owner can connect token channels and their own-app channels and have new items shared, see what was sent, and re-send a failure. Steps 6 to 10 complete the release.

## 1.1

- [ ] Second network group: Reddit, Discord, Google Business Profile, Tumblr.
- [ ] AI captions through the site's AI connection, left as suggested shares in the planner.
- [ ] Hooks for other plugins: `pollen:ShareRequests` (ask Pollen to share), `pollen:ShareText` (change the words), `pollen:ShareSent` (hear how it went).
- [ ] First comments where the network allows them, and the link in the first comment.
- [ ] Reports: shares by channel and by item.
- [ ] A "Check every channel" action.
- [ ] Fixes found while parking: the Facebook, X renewal, Pinterest and Bluesky tests failed on the parked branch.

## 1.2

- [ ] Third network group where APIs and terms allow: VK, Plurk, Blogger, another WordPress site.
- [ ] Image copies resized and cropped per network, and the watermark, served from Pollen's own address.
- [ ] Import from WordPress: FS Poster's and Revive Social's settings, as switched-off automations.

## Can't yet

Needs a socket Teme doesn't have, or a network API that isn't open; see FINDINGS.md and FEATURES.md.

- Image uploads to X, Bluesky, Mastodon and LinkedIn, Flickr, TikTok photos (no binary or multipart outbound door).
- A shared connected-accounts socket (FINDINGS W1).
- The item's featured image (W3).
- YouTube community posts, Xing, Medium, Truth Social, Odnoklassniki (no open API, or terms forbid).
