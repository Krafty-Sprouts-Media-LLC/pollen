# Decisions

Each decision Pollen's build made alone, and why.

## D1. What 1.0.0 is, and what waits for later versions

The owner asked to build one feature at a time and to leave some things for future versions. A first scope I wrote went beyond the brief; on the maintainers' architect's word, confirmed by the owner, it was cut to this.

**Pollen 1.0.0 is only:** channels for Facebook Pages, X, LinkedIn and Telegram; posting on publish with a template per network; the editor panel to choose channels and edit the text; the log with re-send; and notices when a channel needs connecting again. ROADMAP.md has the eight steps in order.

**Moved out of 1.0.0:** Mastodon, Bluesky, Instagram, Threads, Pinterest (later, a group at a time); re-sharing older items (1.1); the planner and composer (1.2); AI captions, images, the hooks for other plugins and reports (1.3); the WordPress import. The code written early for these stays on `parked/all-at-once`, untested.

The older table below is superseded; it is kept so the reasons still read.

**First scope (superseded):** the paragraphs and table that follow described re-sharing and more networks in 1.0.0.

**In 1.0.0**, each built in this order, each with a failing test first:

1. Post on publish through queued jobs, with retries, a failure state, and holding shares while a channel must be connected again.
2. Templates: Pollen's text, a text per network, a text per channel; variables; length fitted per network; link handling; UTM tags.
3. The first network group, one at a time: Mastodon, Telegram, Bluesky (typed tokens), then X, Facebook Pages, Instagram, Threads, LinkedIn, Pinterest (sign-in with the owner's app).
4. Pollen's area: channels, the planner and log (one table of shares), and simple figures.
5. The editor panel: plan shares for this item, turn sharing off for it.
6. Automations: share on publish by type and category to chosen channels with a template, and re-share older items on a timetable (Revive Social's core).
7. Notices when a channel must be connected again or keeps failing.

**Later versions** (the code written early is kept on the `parked/all-at-once` branch, untested, as a start):

| Feature | Planned for | Why it waits |
|---|---|---|
| Second network group: Reddit, Discord, Google Business Profile, Tumblr | 1.1 | The first group is the brief's priority and is finished first. |
| Third group where APIs allow: VK, Plurk, Blogger, another WordPress site | 1.2 | Smaller audiences; same reason. |
| AI captions through the site's AI connection | 1.1 | Needs the panel and planner settled first; the panel can't show captions inline (FINDINGS W9). |
| Image copies resized per network, and the watermark | 1.2 | Needs an image to start from, which Teme doesn't hand plugins yet (W3); most networks can't take an upload (W5). 1.0.0 sends the image's own address. |
| Import from FS Poster and Revive Social | 1.2 | Only options come across, and they become switched-off automations; low value until channels exist. |
| `pollen:ShareRequests`, `ShareText`, `ShareSent` hooks for other plugins | 1.1 | A public API is a promise; it ships once the sharing it describes is stable. |
| Reports by channel and by item | 1.1 | The overview's figures and the log cover 1.0.0. |
| First comments, per-network image sizes, link-in-comment | 1.1 | Each is per-network detail best added with its network's tests. |
| "Check every channel" action | 1.1 | Saving a token channel checks it already. |

## D2. File headers and `@since`

The brief's code standards say "no file headers" and "`@since` only on what Pollen publishes", and its last line says "use proper file headers/comments and `@since` tags". The last line is the later instruction, so every PHP file opens with a short header naming what it holds, and every class and public method carries `@since 1.0.0`. Teme neither reads nor checks `@since` in a plugin (hooks.md), so this costs nothing at run time.

## D3. Tokens are sealed in Pollen's table under a lock key

A plugin can't write a secret setting (FINDINGS W2). Pollen asks for one secret, the lock key, and seals each channel's credentials with libsodium's secret box under a key derived from it. Tokens are never stored in plain text, logged or sent to a browser.

## D4. The share table is both the planner and the log

Teme gives an area three kinds of record (W8). A share row moves from planned to sending to sent or failed (or held, suggested, cancelled), so one table is the plan, the calendar's source and the log, and Duplicate on a row sends it again.

## D5. A minute pass does the planning

A publication is only recorded when Teme announces it; Pollen's minute schedule, running as the administrator who enabled Pollen, reads the item and plans its shares, then queues one send job per share whose time has come (W11). The delay after publishing defaults to two minutes.

## D6. Tests run on their own database

Tests run from teme-dev on `pollen_test`, with `TEME_VERSION=1.0.0` set for the test process only (`scripts/test.ps1`), because teme-dev's `.env` names 0.8.0-alpha.1 (FINDINGS, handbook 8). teme-dev's code and `.env` are unchanged so far.

## D7. Step 1 was tested after its code, not before

The first all-at-once build wrote step 1's code before its tests. When the build was cut back to one feature at a time, step 1's code was brought back from that work and its 12 tests were written against it and run, rather than seen to fail first. Steps 2 and 3 followed the rule: their new tests were run and seen to fail for the expected reason before the code was written. Where a test in a later step passed at once because earlier code already did the job (for example the link and UTM checks in step 2), it is a regression test, not a new feature.

## D8. Where Pollen lives, and how its scripts find the development site

Pollen's folder was built beside the development site, in `Downloads/Projects/teme-plugins`. On 2026-11-22 the owner asked for it to move to `Herd/balafama/workshop/plugins/pollen`. The scripts no longer assume the two sit side by side: they read the development site's path from the `POLLEN_TEME_SITE` environment variable, falling back to `C:\Users\kings\Downloads\Projects\teme-plugins\teme-dev`. Pollen was not added to the Herd site's plugin paths: nothing asked for that, and only one session at a time should change a site.
