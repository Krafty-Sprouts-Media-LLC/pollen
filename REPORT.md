# Report: where Pollen stands

**Interim report, written 2026-11-22.** Pollen 1.0.0 is not finished. This says what works, what doesn't, where work stopped and exactly how to carry on. The final report replaces this when 1.0.0's steps are all done.

## In one paragraph

Pollen shares a newly published item to a Telegram channel through background jobs, with templates per network, retries, and shares held while a channel needs signing in again. It enables on Teme 1.0 and ran on the development site. 36 tests pass. What's missing is everything an owner needs to use it without a developer: **screens to add and see channels**, sign-in for Facebook Pages, X and LinkedIn, the editor panel, and notices. Work stopped after step 3 of 8.

## What works

Steps 1 to 3 of [ROADMAP.md](ROADMAP.md), each written test-first from step 2 on (step 1's code was brought back from earlier work and tested after, which DECISIONS D7 records):

| Step | What | Evidence |
|---|---|---|
| 1 | An item published, or a scheduled item going live, is planned for each channel that shares its type, and sent by a queued job after the wait in Settings. States: planned, sending, sent, failed, held, cancelled. A refused sign-in holds the share and marks the channel "connect again". A busy network is tried again. Unpublishing cancels waiting shares. A writer can opt an item out. A pause switch stops everything | 12 tests; tried on teme-dev |
| 2 | Pollen's text, a text per network, a text per channel; ten variables; length fitted per network without cutting a link; UTM tags; link as card, text, first comment or none; `{author}` | 8 tests; tried on teme-dev (settings and consent; the text itself only in tests) |
| 3 | A Telegram channel's typed token is checked with Telegram (`check-channel` job); the channel connects or shows Telegram's reason; held shares go once it connects | 4 tests; the job loaded on teme-dev, but the check itself wasn't run there |

Also tested: 9 unit tests (Tiptap text, hashtags, templates, UTM, fitting text, sealing tokens, OAuth 1.0a signing against the published examples, PKCE) and 3 for `{author}`. **36 tests, 84 assertions, all passing** at commit `9eaaec9`.

### Networks

| Network | State |
|---|---|
| Telegram | **Done** (text and link; token checked when saved). No screen to add one yet |
| Facebook Pages, X, LinkedIn (profile and pages) | **Not started** on `main`: step 5. Written but untested on `parked/all-at-once` |
| Everything else | Later versions; see ROADMAP.md and FEATURES.md |

## What doesn't work yet, and why

| Gap | Why | Where it goes |
|---|---|---|
| No way to add or edit a channel from the admin | Pollen's area (screens) is step 4 | Next |
| No log screen, no re-send button | Same | Step 4 |
| Facebook Pages, X, LinkedIn can't connect | Need the connect and callback routes, app keys in Settings, and renewal: step 5 | Step 5 |
| No editor panel | Step 6 | After |
| No notices | Step 7 | After |
| No README steps for the app keys' screens, as they don't exist | Step 8 | At the end |
| A writer can't choose a time to share | Needs the panel and area | Steps 4 and 6 |
| Only items published after enabling Pollen are heard | By design | |
| Item image, owner and the site's time zone aren't readable by plugins; no file uploads from the outbound door | Teme walls W3, W3b, W4, W5 in [FINDINGS.md](FINDINGS.md) | Core, later |
| Mastodon can't be tested through the kit | The kit can't agree to a host typed into settings (FINDINGS, testing kit 5) | Later group |

## Totals

| | |
|---|---|
| PHP source files | 34 (about 3,300 lines, with blank lines and docblocks) |
| Test files | 9 (36 tests, 84 assertions) |
| Tables | 3: `pollen_channels`, `pollen_shares`, `pollen_items` |
| Jobs | 3: `dispatch` (each minute), `send-share`, `check-channel` |
| Manifest | 4 capabilities, 4 hooks, 1 host (`api.telegram.org`) |
| Commits | 12 on `master` (pushed), plus the parked branch |
| Documents | README, CHANGELOG, CONTRIBUTING, AGENTS, ROADMAP, FEATURES, DECISIONS, FINDINGS, this report |
| Walls found in Teme | 14 (FINDINGS W1 to W13 and W3b), plus 9 handbook gaps and 9 about the testing kit and the trial |

## How to try it on teme-dev

Needs Docker's PostgreSQL up (the `postgres_test` and the dev Postgres on port 5442 were used), and the databases `pollen_test` and `pollen_dev` (made). From Pollen's folder:

```powershell
powershell -NoProfile -File scripts/test.ps1                      # the tests
powershell -NoProfile -File scripts/dev.ps1 migrate --force       # pollen_dev
powershell -NoProfile -File scripts/dev.ps1 serve                 # http://127.0.0.1:8199
```

On the trial site, an administrator `admin@pollen.test` exists; its password and the trial lock key are in `scripts/dev-account.txt` (git-ignored, made-up values for that local database only). Pollen is enabled there with "Pause all sharing" on and one channel with a made-up token, so nothing can reach Telegram. The trial server isn't running; `dev.ps1 serve` starts it. Without a screen to add channels, a channel is added through Pollen's own store in `artisan tinker`: `Channels::save()` with a token sealed by `Vault` (the `channel()` helper in `tests/Support.php` shows the shape).

Things I did on this machine, for whoever carries on: started Teme's `postgres_test` container (`docker compose up -d postgres_test` in teme-dev); made the databases `pollen_test` (on port 5433) and `pollen_dev` (on the 5442 server). **teme-dev's code and `.env` are unchanged.**

## Where I stopped, and what to do next

I stopped after step 3, at commit `9eaaec9`, then the docs commit that carries this report. Next is **step 4: Pollen's area**. A way to carry it out:

1. **Failing test first.** Teme's kit can't drive an area directly (FINDINGS, testing kit 6), so test what the area calls: a `Channels` store save that seals a token and queues `check-channel`, access checks by permission, the log's rows. Then the area class on top of them.
2. **Manifest.** Declare two permissions (`share` for editors and authors, `manage` for editors; administrators hold both) and `contributes.area` with pages Overview, Channels and Log, and records `channel` and `share`. Known limits found by enabling: a figures block holds 1 to 4 figures; labels are 1 to 40 characters; badge tones are `ok`, `neutral`, `info`, `warn`, `danger`; row actions are `edit`, `duplicate`, `enable`, `disable`, `delete`. Add `notices.send` only with step 7.
3. **Reference code.** `git show parked/all-at-once:src/Area/PollenArea.php` and the manifest on that branch hold a worked version, including the channel form, the Sign-in column, the shares table with re-send (Duplicate), and charts. Bring back only what step 4 needs: channels and the log. Leave the planner form, the composer, automations and reports for 1.1 to 1.3.
4. **Channel form for 1.0.0:** name, network (Telegram until step 5), account, labels, content types shared on publish, token, link choice, image choice, text, who may post. Saving a token seals it with the lock key and queues `check-channel`; it never goes in the job's payload. Refuse a save if the lock key isn't set.
5. **The log:** one table of shares with filters by status, the network's reply, and Duplicate as the re-send. Scope it by `share` and `manage`: a writer sees their own, a manager sees all.
6. **Try it on the trial site:** enable (a new permission waits for agreement), add a channel in the admin, see the status change, with sharing still paused. If the browser pane won't hold a sign-in, set the password again with the invitation page and confirm with `teme:user:create`.
7. Update ROADMAP, CHANGELOG and FINDINGS; commit and push.

After that, step 5 (own-app sign-in: Facebook first, then X, then LinkedIn, each with its terms and limits written into DECISIONS.md), then the panel (6), notices (7) and the docs and final report (8). Don't start 1.1 before 1.0.0 is usable (steps 1 to 5 and 8).

## Decisions worth knowing about

- 1.0.0 was cut to Facebook Pages, X, LinkedIn and Telegram on the maintainers' architect's word, which the owner confirmed (DECISIONS D1). Mastodon, Bluesky, Instagram, Threads, Pinterest, automations, re-sharing and the planner moved out.
- The owner asked for file headers and `@since` tags, which differs from the brief's code standards (D2).
- Pushing to `origin/main` after every commit was confirmed by the owner.
- Tokens are sealed under a lock key because a plugin can't write secrets (D3, W2).

## Open questions for the owner

1. Is the lock key acceptable (one phrase set by an administrator), or should Pollen wait for Teme's connected-accounts socket before connecting accounts at all?
2. Should `{author}` stay on the publisher fallback until Teme exposes an item's owner?
3. The folder now lives in `Herd\balafama\workshop\plugins`; the scripts find the development site by `POLLEN_TEME_SITE` or by its old path. Should that site be added there too?
