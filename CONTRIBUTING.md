# Contributing to Pollen

Notes for the people and agents who maintain Pollen. Site owners want [README.md](README.md).

## What to read first

1. [ROADMAP.md](ROADMAP.md): what 1.0.0 is, the steps and where each stands. Don't start a later version before 1.0.0 is usable.
2. [DECISIONS.md](DECISIONS.md): what was decided alone and why.
3. [FINDINGS.md](FINDINGS.md): every wall (a socket Teme doesn't have) and every place the handbook was missing or wrong.
4. [FEATURES.md](FEATURES.md): what FS Poster and Revive Social do, and what Pollen does about each.
5. Teme's developer handbook in the development site, `docs/guides/developers/` (start with `README.md` and `for-agents.md`), and its generated references in `docs/reference/`.

Pollen uses **only** what an outside developer has: the handbook, the generated references, `teme/contracts` and `teme/testing`. It never names a class in Teme's own `App` namespace, never reaches for the container, and doesn't change Teme. If Teme lacks something, write it in FINDINGS.md as a wall and do without it.

FS Poster and Revive Social are GPL. They were read for **features and behavior only**. Don't copy their code, markup, wording, icons or setting names, and don't use their licence, updater or remote-service code.

## How the code is laid out

```
teme-plugin.json     the manifest: capabilities, hosts, jobs, settings, hooks, tables
database/migrations  Laravel migrations for Pollen's own tables (up() only)
src/Pollen.php       the plugin class: hooks it hears, the handlers of its jobs
src/Doors.php        the doors Teme hands Pollen, and the stores, in one holder
src/Settings.php     Pollen's settings group and its sealed lock key
src/Compose/         turning an item into text: template, variables, hashtags, UTM, length
src/Networks/        one class per network behind Network; Http maps answers to failures
src/Channels/        a channel value, and the Connector that checks a typed token
src/Sharing/         Planner (what to share), Dispatcher (the minute's pass), Sender (one share)
src/Store/           Pollen's own tables, through Laravel's query builder, one class each
src/Connect/         OAuth 1.0a signing and PKCE (OAuth 1.0a waits for Plurk and Tumblr)
src/Jobs/Handler.php a job handler made from a function
tests/               Pest tests on Teme's kit; Support.php holds the helpers
scripts/             test.ps1 and dev.ps1, see below
```

Tables are read and written only in `src/Store/`, so a Laravel upgrade touches one place. A table's name starts with `pollen_` and is listed under `owns` in the manifest.

### Adding a network

A network is a class that implements `Network` (its key, label, limits, and `publish()`), and then `TokenNetwork` (a typed token, checked by `connect()`) or `OAuthNetwork` (sign-in with the owner's app). Register it in `Networks/Catalog.php`, name its host under `hosts` in the manifest, add it to the "Text for each network" select, record its terms and limits in DECISIONS.md, and write its tests first. `Networks/Http.php` turns a network's answers into `ShareFailed` with one of three kinds: retry, final or reconnect. Use those; don't invent states.

## The rules of the code

- **A failing test first**, then the code, then the test run again, then a commit, then a push. Tests run on PostgreSQL only. A test never calls a real network and never holds a real token: outside calls go through the kit's `outbound()` fake, and a token in a test is a made-up value.
- **No file headers** is Teme's rule for plugins, but this project's owner asked for a short header on every PHP file naming what it holds, with `@package` and `@since 1.0.0`, and a one-to-three-sentence docblock on every class and `@since 1.0.0` on public methods (DECISIONS.md D2). Keep to that.
- `declare(strict_types=1);` in every PHP file. Pint for formatting.
- Every word a person reads goes through `__()` with the English sentence as its key, in American English. Keep menu entries and headings saying "Pollen". Translations go in `lang/<locale>.json`.
- Pollen acts only for the person who started the work. Scheduled work runs as the administrator who enabled the plugin. A job reads an item with `ContentReader` for `JobContext::personId()`.
- Outside hosts are listed in the manifest, or agreed through `hostsFrom`. Pollen calls no other.
- A secret never goes in a file, a log, a payload, a test, or a message. Channel tokens are sealed by `Store/Vault`; a job's payload holds a channel's ID, never its token.
- Don't track readers.

## Running the tests

The tests run inside a Teme checkout, the development site. Pollen's own database is `pollen_test` (the other databases belong to other plugins). PostgreSQL must be up: `docker compose up -d postgres_test` in the development site, then create the database once: `CREATE DATABASE pollen_test`.

```powershell
powershell -NoProfile -File scripts/test.ps1                   # everything
powershell -NoProfile -File scripts/test.ps1 -Path tests/Unit  # the quick ones
powershell -NoProfile -File scripts/test.ps1 -Filter "Telegram"
```

The script finds the development site through the `POLLEN_TEME_SITE` environment variable, or at `C:\Users\kings\Downloads\Projects\teme-plugins\teme-dev`. It sets `DB_DATABASE=pollen_test` and `TEME_VERSION=1.0.0` for its own process, because that site's `.env` still says an older Teme. A first run takes a couple of minutes while the database is migrated; after that each test takes seconds.

On this machine `php` and `composer` are Herd's `.bat` shims, found only from PowerShell, and the Bash tool mangles backslashes in PHP code. Run PHP through PowerShell and edit PHP with an editor, not with `sed` or a shell here-document.

## Trying it on the development site

`scripts/dev.ps1` runs Teme's own commands, or serves the site, against Pollen's database `pollen_dev`, with only Pollen as a plugin and Teme reporting 1.0.0. Nothing in the development site's `.env` is changed, and only one session should be using that site at a time.

```powershell
powershell -NoProfile -File scripts/dev.ps1 migrate --force
powershell -NoProfile -File scripts/dev.ps1 teme:user:create --name="Pollen Admin" --email=admin@pollen.test
powershell -NoProfile -File scripts/dev.ps1 teme:plugins:enable krafty-sprouts/pollen --agree --as=admin@pollen.test
powershell -NoProfile -File scripts/dev.ps1 serve              # http://127.0.0.1:8199
powershell -NoProfile -File scripts/dev.ps1 teme:plugins:run-due-schedules --plugin=pollen
powershell -NoProfile -File scripts/dev.ps1 queue:work --stop-when-empty
```

Use a real queue worker: the console's schedule runner cancels a plugin's job on the `sync` queue (FINDINGS, trying it on a development site 1). A test account's password goes in `scripts/dev-account.txt`, which git ignores.

**Never send a made-up token to a real network.** On the trial site, keep "Pause all sharing" on unless you have a real account you mean to post to, and say so before turning it off.

## Branches and commits

- `master` tracks `origin/main` (private: `Krafty-Sprouts-Media-LLC/pollen`). Commit after every step and push after every commit.
- `parked/all-at-once` holds code written ahead of its turn: 18 networks, the admin area, the editor panel, AI captions, image copies, the WordPress import and the re-share engine. It is **untested** and not part of 1.0.0. When a later version's turn comes, bring a piece back with `git show parked/all-at-once:path` and write its failing test first. Don't merge the branch.
- Commit messages are short and say what and why. Commits end with the attribution line the session's instructions give.

## Releasing

When ROADMAP.md's 1.0.0 steps are all done: run the whole suite, try the release on the development site, move CHANGELOG.md's entry from "in progress" to a dated release, write REPORT.md, and tag `v1.0.0`.
