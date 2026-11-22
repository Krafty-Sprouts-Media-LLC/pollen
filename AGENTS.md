# Pollen: notes for agents

Pollen is a Teme plugin (`krafty-sprouts/pollen`) that shares a site's content to social networks: on publish today; on a plan and by re-sharing older items in later versions.

- **Capabilities** (see `teme-plugin.json`): `content.read`, `people.read`, `http`, `jobs`. Hooks: `ContentPublished`, `ContentUnpublished`, `ContentTrashed`, `ContentDeleted`. Jobs: `dispatch` (each minute), `send-share`, `check-channel`.
- **Where it stands:** read [ROADMAP.md](ROADMAP.md) first, then [REPORT.md](REPORT.md) for where work stopped and what to do next.
- **Tests:** `powershell -NoProfile -File scripts/test.ps1`. They run inside a Teme checkout on the `pollen_test` database, written with Teme's testing kit. See [CONTRIBUTING.md](CONTRIBUTING.md) for setup and the rules.
- **Build one feature at a time**, each from a failing test. Don't build a later version's work; the early code for it is parked on `parked/all-at-once`.
- **Use only** Teme's handbook (`docs/guides/developers/`), `docs/reference/`, `teme/contracts` and `teme/testing`. Never name a class in Teme's `App` namespace, and don't change Teme.
- **Never** put a real token in a file or test, call a real network from a test, or send a made-up token to a real service.
- Handbook: [Your first plugin](https://github.com/temecms/teme/blob/main/docs/guides/developers/start/first-plugin.md) and [Hooks](https://github.com/temecms/teme/blob/main/docs/guides/developers/concepts/hooks.md).
