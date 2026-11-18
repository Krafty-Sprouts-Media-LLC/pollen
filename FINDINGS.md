# Findings

What building Pollen against Teme 1.0.0's public handbook, contracts and testing kit taught us: where the handbook was missing, wrong or unclear, and each **wall**, a socket a plugin like Pollen needs and Teme doesn't offer yet. Each says what we expected and what Pollen does instead. Nothing here was worked around by changing Teme.

## Walls

### W1. A shared "connected accounts" socket

**Expected.** One place in Teme where an administrator connects an outside account once (a Facebook Page, a Mastodon account, a Google profile) and several plugins use it, the way Settings › AI gives every plugin one AI connection.

**Instead.** Pollen keeps its own channels, its own sign-in flow and its own sealed tokens (W2).

**What such a socket would need for Pollen to move onto it:**

1. **Providers, declared by core or by a plugin.** A provider is a network: its key, name, the kind of sign-in (OAuth 2 with or without PKCE, OAuth 1.0a, a typed token or app password, a webhook address), its consent and token addresses, the scopes a plugin may ask for, and how it renews access. Pollen's `src/Networks/*` hold exactly this for 18 networks and could be offered as providers.
2. **App keys kept by core.** The site owner's client ID and secret for each provider, entered once in a core screen, kept as secrets, never handed to a plugin's code (core performs the token exchange).
3. **The sign-in flow run by core.** A "Connect" button in the admin that sends the person to the provider and back to a core callback, with state and PKCE kept by core. Today a plugin must answer a public route and show its connect address as text (W6).
4. **Accounts as records with sub-accounts.** One sign-in can reach several Pages, boards, locations or blogs; the socket needs the account, its sub-accounts with IDs and names, and which sub-account a use means.
5. **Credentials stored as core secrets** (encrypted, never cached, never sent to a browser), with their expiry and refresh token, renewed by core on a schedule, and a state per account: connected, must reconnect, revoked.
6. **Consent per plugin and per scope.** "Pollen may post to Garden club page" agreed by an administrator, like hosts and AI tasks are; a plugin sees only the accounts it was granted, by an opaque ID.
7. **A door to use an account.** Either core signs the request (`ConnectedAccounts::request($accountId, $method, $url, $body)` adds the token, refreshes on 401, and marks the account for reconnecting), or it hands the plugin a short-lived access token for one call. The first keeps tokens out of plugin memory entirely.
8. **Hooks** when an account must be reconnected, or is removed, so plugins can hold their work and notices can go to the right people (Pollen sends its own today).
9. **Binary uploads** through that door (W5), since most networks upload images that way.
10. **Who may use an account**: per-account permissions by role or person, which Pollen keeps itself today (`access` and `people` on each channel).

Moving Pollen onto it would replace `pollen_channels.sealed`, `pollen_connects`, the lock key, the connect and callback routes, and each network's `authorizeUrl`, `exchange` and `refresh`; the channels would keep only Pollen's own choices (labels, templates, link and image modes).

### W2. A plugin can't keep a token it received as a secret

**Expected.** OAuth gives Pollen access and refresh tokens when someone connects a channel. The rules say a token lives only in a `secret` setting read through `SecretReader`.

**Found.** `PluginSettingsWriter` never writes a `secret` (settings.md), and a secret setting is one fixed key, not one per channel. The import guide also says to keep imported tokens as secrets, which a plugin can't write either.

**Instead.** Pollen asks the administrator for one secret setting, its *lock key*, and seals each channel's tokens with libsodium's secret box under a key derived from it (`src/Store/Vault.php`), in its own table. Tokens are never stored in plain text, never logged, never sent to a browser; changing the lock key means connecting every channel again. A core socket (W1) would make this unnecessary.

### W3. No featured image, no excerpt and no media reader for plugins

**Expected.** To share an item with its image, a plugin needs the item's featured image (its public address, size and alt text) and its excerpt.

**Found.** `ItemRef` and `ItemSnapshot` carry neither. `NewItem` and `ItemChanges` can *set* a featured image, but nothing reads one; `MediaRef` has no address, and no door turns a media ID into an address. Teme stores media in content by ID (rule 9), so an article's image nodes may carry no address either.

**Instead.** Pollen uses, in order: an image address the writer gives (editor panel or planner), then the first image node in the article's document that carries an absolute `src`. An `excerpt`, `summary` or `description` field is used when the type has one; otherwise Pollen cuts the article's text. Settings say so in words.

### W3b. An item's owner isn't readable

`ItemSnapshot::$credits` is empty "when it credits its owner", and nothing hands a plugin the owner. A share's `{author}` would be empty for most items. Pollen records the person `ContentPublished::$actorId` names (who published) and uses them when there are no credits. That is wrong when an editor publishes an author's post, and empty for items published by schedule with no person. Expected: `ItemSnapshot::$ownerId`, or credits always filled.

### W4. No site time zone outside the admin area

**Expected.** `SiteInfo` to give the site's time zone, for "share at 09:30" and "not before 8 o'clock".

**Found.** Only `AreaContext` carries a time zone (the viewer's). Jobs, panels and routes have none.

**Instead.** Pollen remembers the time zone each person last used Pollen's area in (`pollen_people.zone`), and each automation keeps the one it was saved in. A writer who never opened the area gets UTC; the panel's label says to write a date and time, and README says which zone.

### W5. The outbound door can't upload files

**Expected.** Most networks upload an image as binary or multipart data: X, Bluesky (`uploadBlob`), Mastodon, LinkedIn's image upload, Flickr, TikTok.

**Found.** `OutboundHttp` offers `get`, `postJson` and `postForm` only.

**Instead.** Pollen sends images only to networks that fetch them by address (Facebook, Instagram, Pinterest, Threads, Telegram, Discord, Google Business Profile, Tumblr, Blogger, WordPress), from the image's own address or from Pollen's prepared copy at `/pollen/image/{key}.jpg`. On X, Bluesky, Mastodon and LinkedIn the link's preview card carries the picture. Flickr and TikTok are "can't yet". A `put($url, $bytes, $type)` or multipart `postFiles()` would open them.

### W6. An area can't send a person to another site

**Expected.** A "Connect" row action or link cell that opens the network's sign-in.

**Found.** Cells are text, number or badge; row actions are the five built-ins; blocks have no link. Notices may link only to the plugin's own screens.

**Instead.** Each channel's *Sign-in* column shows the address `https://site/pollen/connect/<40 random characters>` as text, to open in the browser. That public route redirects to the network (a `location` header is allowed), and `/pollen/callback` finishes.

### W7. Area and panel fields can't offer the plugin's own choices

**Expected.** A select or multiselect whose options the plugin gives at run time (Pollen's channels), and a picker for one item.

**Found.** Field `options` are fixed in the manifest. The area's `terms` field stores term **IDs**, while `ContentReader` filters by **slugs** and nothing maps one to the other. There is no item picker.

**Instead.** Channels, labels, categories and tags are typed as comma-separated words and checked when saved (an unknown one is refused with its name). An item is named by its address or ID.

### W8. Only three kinds of record, four figures, no calendar

**Expected.** Records for channels, planned shares, automations, the log and templates; a calendar block for the planner.

**Instead.** Three records: channels, shares (the planner and the log at once: a share is planned, suggested, held, sent or failed) and automations (on-publish rules and re-share schedules in one record, by kind). Templates live in Settings (a repeater per network) and on each channel. The planner is a bar chart of the next 30 days above a table sorted by time. The overview shows four figures.

### W9. The editor panel can only suggest changes to the article

**Expected.** To preview each network's post, edit its text per network, and show what was shared, inside the editor.

**Found.** A panel shows one status line and suggestions that change the article (title, field, terms, link, body); action forms hold four fields.

**Instead.** The panel's status line summarises the item's shares; *Plan shares* takes channels, words, a time and an image; *Suggest captions* asks the AI and leaves one suggested share per channel in the Planner, where the writer edits each network's text and chooses it (Enable); *Sharing on or off* opts the item out. Previews are the Planner's rows: the exact text each network got or will get.

### W10. No way for another plugin to ask Pollen for something directly

**Expected.** A plugin such as Schedulely calling "share this item now".

**Found.** A plugin's own hooks flow from it to its listeners: it can notify, transform or collect, but nothing lets another plugin call into it.

**Instead.** Pollen declares `pollen:ShareRequests`, a Collect hook it asks each minute; a listener returns requests with a unique key, which Pollen plans once. `pollen:ShareText` (Transform) and `pollen:ShareSent` (Notification) complete the pair.

### W11. Scheduled work far ahead, and who a scheduled publication acts for

`QueueOptions` delays a job by an hour at most, so Pollen keeps its own plan and a minute schedule queues what is due. `ContentPublished` for a scheduled item may carry no person, and a listener with no signed-in person can't queue a job, so Pollen's listener only records the publication; the minute pass, running as the administrator who enabled Pollen, reads the item and plans its shares.

### W12. A job knows only who it runs for

`JobContext` gives a person's ID, not their role or the plugin's permissions, so a job can't check "may this writer post to this channel". Pollen records the permissions core passes to its panel and area (`pollen_people`) and checks those.

### W13. The import socket for a sharing plugin

- `options()` returns nothing, so what couldn't come across can't reach the run's report; Pollen writes it into each imported automation's note instead.
- `PluginSettingsWriter` can't write a repeater or a secret, and an importer has no person to write as, so imported templates become automations (switched off) rather than settings.
- A plugin's own WordPress tables aren't read, and FS Poster keeps its channels, planners and schedules in tables; only its options come across.
- `ImportScope` maps posts and people, not terms, so Revive Social's term filters come across by term name, as slugs.

## Handbook: missing, wrong or unclear

1. **The area's blocks and records are described only in prose.** The schema stops at `pages: [object]`; the exact keys of a figures item, a chart series, a table's columns and filters, and a record's sections had to be guessed and corrected by enabling. Two limits surfaced only as refusals: a figures block holds 1 to 4 figures, and a panel field's label is 1 to 40 characters.
2. **`Collect::itemType()` and `Transform::valueType()` are static**, which the contracts reference doesn't show; PHP stops at a fatal error instead of the plugin being refused with a sentence.
3. **The manifest reference says a route's handler is "built with no constructor arguments"**; the public-routes guide says it is built with the doors its constructor asks for. The guide is right: Pollen's handlers ask for settings, secrets, the site and the outbound door.
4. **A panel action's payload** is documented only for `itemId`. Pollen reads the form's values from `values`, `fields` or the payload itself.
5. **A badge's tones** (`ok`, `neutral`, `info`, `warn`, `danger`) appear only in `Cell::TONES`.
6. **`HttpAnswer::$headers`** has no documented shape (list of values or one string); Pollen accepts both.
7. **Notice buttons** may lead to the plugin's own area via `plugins.area.show`, but the route's parameter name isn't given. Pollen sends `['code' => 'pollen']` and, if that is refused, the notice without its button.
8. **The development site's `.env` names `TEME_VERSION=0.8.0-alpha.1`** while its contracts are at 1.0.0, so every plugin requiring `^1.0` is refused there. Pollen's test script sets `TEME_VERSION=1.0.0` for its own process; teme-dev was not changed.
9. **Plugins' words.** The rules say a plugin's `lang/<locale>.json` adds sentences. The code standards say "Teme loads the active theme's own lang folder, but not a plugin's, so a plugin's words show in English." These disagree; Pollen writes every word through `__()` in American English and ships `lang/` for translators.

## Trying it on a development site

1. **A plugin's job queued from `teme:plugins:run-due-schedules` on the `sync` queue is cancelled at once** ("Cancelled by …"), because plugins don't start in that command, and `sync` runs the job inside it. With the site's database queue and `queue:work`, the same run finishes. The handbook's development-site guide says to run a worker; it could say that the `sync` queue won't run plugin schedules from the console.
2. **The editor's address for an item is `/admin/content/{id}/edit`**, not under the type's list (`/admin/posts/{id}`); the handbook doesn't name it, which a plugin needs for links in notices and README steps.
3. **Teme's editor has an excerpt box and a featured image picker**, so the item has both; neither reaches plugins (W3).

## Testing kit

1. **A plugin's classes load only after `plugin()`**, so a test that uses Pollen's classes before enabling needs the package's own autoloader (`tests/bootstrap.php`).
2. **The outbound fake answers one body per host**, whatever the path, and `answerInTurn()` takes statuses only, not bodies. A network that answers a session, then a post, then a comment needs one body holding every key the plugin reads.
3. **`SentRequest::$headers` holds a list of values per header.**
4. **`runJob()` always runs attempt 1**, so a job's last-try behaviour can't be reached through the kit.
5. **`changeSettings()` can't agree to a host.** Saving a new host in a `hostsFrom` setting needs the person to tick "Saving lets the plugin connect to…", which the kit has no way to do; the save is refused with "Agree to let Pollen connect to …, or remove them." So a network on a server the site chooses (Mastodon, a WordPress site, a Bluesky server of one's own) can't be reached in a kit test. Pollen's first tests use Telegram, whose host the manifest names. Expected: `changeSettings([...], agreeHosts: true)` or `agreeTo('host')`.
6. **Nothing in the kit drives a plugin's area, panel or status check** with Teme's own doors and contexts: no `area()->save()`, `panel()->view()`. Pollen's tests reach those through the jobs and routes they share, and test the rest of the area through its tables.
