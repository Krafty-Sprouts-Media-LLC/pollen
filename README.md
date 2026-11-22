# Pollen

Pollen shares your TemeCMS site's content to social networks: when an item is published, and (in later versions) on a plan and by re-sharing older items. Like pollen, it carries your work to places that can't see your site.

By Krafty Sprouts Media, LLC. Package `krafty-sprouts/pollen`, plugin code `pollen`. Needs Teme 1.0 or later.

> **Where this stands.** Pollen 1.0.0 is being built one feature at a time and is **not finished**. Today it shares a newly published item to a Telegram channel through background jobs, with a template per network, retries, and shares held while a channel needs signing in again. You **can't yet add a channel from the admin**: Pollen's own screens come next. Facebook Pages, X and LinkedIn, the editor panel and notices follow. [ROADMAP.md](ROADMAP.md) says exactly where each stands.

## What Pollen does

- **Shares on publish.** When an item of a type you choose is published, or a scheduled item goes live, Pollen shares it to the channels you chose, after a wait you set. It never works while a visitor loads a page: everything runs as queued jobs.
- **Writes for each network.** One template for everything, a text for each network, and a text for each channel. It fits the text to the network's length limit without cutting a link, and can tag links with UTM campaign tags.
- **Holds, retries and tells you.** A busy network is tried again. A channel whose sign-in stopped working is marked "connect again", and its shares wait instead of failing. A refusal is recorded with the network's own words.
- **Respects the writer.** A writer can turn sharing off for one item.
- **Sends only where you tell it.** Pollen talks only to the networks of the channels you connect, through Teme's outbound door, which lists every host. It tracks no readers.

Planned for 1.0.0: Facebook Pages, X and LinkedIn channels; Pollen's screens for channels and the log with a re-send; a panel in the editor to choose channels and edit the text per network; and notices when a channel needs connecting again. Later versions: re-sharing older items (1.1), a planner and composer (1.2), AI captions, image copies, and hooks for other plugins (1.3), then more networks a group at a time.

## Install

Pollen isn't published yet. To try it, put its folder where your Teme site can load it:

1. In your site's `.env`, add the folder's absolute path to `TEME_PLUGIN_PATHS` (separate several with commas).
2. Run `php artisan migrate`, then start the scheduler and a queue worker (`php artisan schedule:work` and `php artisan queue:work`). Pollen's jobs and its minute-by-minute pass don't run without them.
3. Enable it under **Plugins**, or with `php artisan teme:plugins:enable krafty-sprouts/pollen --agree --as=you@example.com`. Scheduled work runs as the administrator who enabled the plugin, so the console form needs `--as`.

Teme shows what Pollen asks for, and nothing starts until you agree:

| It asks to | Why |
|---|---|
| Read your posts, pages and other content, including drafts | To write the share from the item as it is when it goes out, and to read a writer's own drafts for the editor panel |
| See the names of the people who write on this site | For `{author}`, and to name who may post to a channel |
| Connect to services outside this site: `api.telegram.org` | To post. More hosts are listed as networks are added |
| Run work in the background and on a schedule | Sharing never happens while a page loads |

## Set it up

Open **Settings › Pollen**.

1. **Lock key.** Type a long phrase of at least 24 characters. Pollen seals each channel's sign-in with it, so a stolen database alone can't use your accounts. Choose it once and keep it: if you change it, every channel must be connected again. Teme never shows it back.
2. **Share text.** What a share says unless a network, a channel or a writer says otherwise.
3. **Text for each network.** Optional. Add a row to write differently for Telegram (and, as they arrive, the other networks).
4. **Wait after publishing.** Minutes before an item is shared, so a quick fix to a typo makes it in. The default is 2.
5. **Tag shared links.** Adds `utm_source`, `utm_medium` and `utm_campaign` to each link, so your own statistics can tell where readers came from. Each takes a pattern.
6. **Pause all sharing.** While on, nothing is sent; shares wait and go when it is off.

### What a template can say

| Variable | What it becomes |
|---|---|
| `{title}` | The item's title |
| `{excerpt}` | The item's excerpt field if its type has one, else the first words of the article |
| `{content}` | The article's text, cut |
| `{link}` | The item's address, with UTM tags if turned on |
| `{author}` | The item's first credited person (see "Known limits") |
| `{hashtags}` | The item's categories and tags as hashtags, up to the number set |
| `{categories}`, `{tags}` | Their names, separated by commas |
| `{site}`, `{type}` | The site's name, the item's content type |

A word Pollen doesn't know is refused when you save. If a text leaves the link out, Pollen adds it. A channel can instead put the link as a preview card, in the first comment, or leave it out. Pollen picks the text this way: the writer's own words, then the channel's, then the network's, then the one for all.

### Hashtags and length

Each network's limit is kept: 4096 for Telegram, 280 for X (a link counts as 23, as X counts it), 3000 for LinkedIn, and so on. A text that is too long is cut at a word with an ellipsis, and a link is never cut.

## Connect a network

### Telegram (works today in code; no screen yet)

1. In Telegram, message **@BotFather**, send `/newbot`, and keep the token it gives you.
2. Add the bot to your channel or group as an administrator who may post.
3. The channel's account is its public name (`@yourchannel`) or its number (for groups, usually starting with `-100`).
4. When Pollen's area has a Channels page (the next step), add a channel, give it the account and token, and save. Pollen asks Telegram whether the bot and chat work, and marks the channel connected, or "connect again" with Telegram's own reason.

### Facebook Pages, X and LinkedIn (coming in step 5)

These networks need an **app of your own**, because the network won't let one plugin sign in for every site. You make the app once, give its keys to Pollen in Settings, and Pollen signs your channels in through it. The keys stay in Teme's secret settings, never in plain text. Pollen's address for the sign-in to come back to will be `https://your-site/pollen/callback`; give that to each network as the redirect address.

| Network | Where you make the app | What Pollen needs |
|---|---|---|
| Facebook Pages | developers.facebook.com, a Business app with Facebook Login | App ID and App Secret; permission to manage a Page's posts |
| X | developer.x.com, a project with an OAuth 2.0 app, "Read and write" | Client ID and Client Secret |
| LinkedIn profile | linkedin.com/developers, a new app with "Share on LinkedIn" | Client ID and Client Secret |
| LinkedIn pages | the same app with the Community Management API added (LinkedIn must approve it) | the same keys |

Each network sets its own limits and may need to review the app before it can post to accounts that aren't yours. Pollen records every network's terms and limits in [DECISIONS.md](DECISIONS.md) as that network is built.

## Privacy

- Pollen sends an item's title, text, link and an image address **only** to the networks of channels you connected, and only when sharing is on.
- It does no tracking of readers, and calls no service run by its author.
- The only data about a person that it keeps is the ID of whoever published each item, so `{author}` has a name when an item credits nobody. Remove the plugin's data (on the Plugins page, once Pollen is disabled) to delete it, along with every table Pollen made.
- Tokens are sealed with your lock key and are never logged, shown, or sent to a browser. A network's reply is kept in short in the log.
- UTM tags only add words to the links you share. If your site measures visits, those words are how you see where they came from.

## Known limits

- **No screens to add channels yet.** The next step builds them.
- **`{author}`.** Teme leaves an item's credits empty when it credits its owner and doesn't tell plugins who the owner is. Pollen uses the first credited person, and when there are none, whoever published the item. That is wrong when an editor publishes an author's post.
- **Images.** Teme doesn't let plugins read an item's featured image. Pollen uses an image a writer gives, else the first image in the article. It can only give a network an image's address; networks that need a file uploaded (X, Bluesky, Mastodon) get the link's card instead.
- **Times.** Teme tells a plugin the site's time zone only inside its admin screens, so when a writer can type a time (the editor panel and planner, still to come), Pollen will read it in the time zone that writer last used Pollen's screens in, or UTC. Today, shares go out after the wait you set, with no clock time.
- **Lock key.** There is one for the whole site.

All of these, and every other place Teme was missing something Pollen needed, are in [FINDINGS.md](FINDINGS.md), written so Teme can add them.

## If something goes wrong

| You see | It means |
|---|---|
| A channel says "Connect again" | The network no longer accepts its sign-in. Its shares are held; open the channel's sign-in address, or retype its token, and save. They go once it is connected |
| "Pollen's lock key changed…" | The key that sealed a channel isn't the one set now. Connect the channel again |
| A share says "Try 1 failed" | The network was busy; Teme tries again after a wait |
| A share is "failed" with words from the network | The network refused it (too long, a duplicate, a closed chat). Fix the cause and use Duplicate to send it again |
| Nothing is shared | Check "Pause all sharing", that a channel lists the item's type, that the scheduler and a queue worker run, and Tools › Jobs |

## For developers

[CONTRIBUTING.md](CONTRIBUTING.md) has the layout, how to run the tests, and the rules. [AGENTS.md](AGENTS.md) is the short version for an agent.

## License

GPL-2.0-or-later.
