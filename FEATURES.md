# Features: FS Poster and Revive Social, and what Pollen does about each

Written from reading FS Poster and Revive Social (Revive Old Posts) for **features and behavior only**. Nothing here is copied from either plugin; both are GPL and Pollen is written fresh. Revive Social was read in its free ("lite") form: where a feature sits behind its paid add-on, that was seen only in labels and gates and is marked *(Pro)*.

**Verdicts**

| Verdict | Meaning |
|---|---|
| **Teme already does this** | Teme core, or another plugin, covers it; Pollen doesn't repeat it. |
| **Same** | Pollen does what the WordPress plugin does. |
| **Different** | Pollen does the job another way; the table says how. |
| **Better** | Pollen does it with less trouble, more safety or more control. |
| **Not in Pollen** | Left out on purpose; the reason is given. |
| **Can't yet** | Blocked by a missing Teme socket or a network API that isn't open; the blocker is named. |

**When** says the Pollen version that carries it. **1.0.0** is Facebook Pages, X, LinkedIn and Telegram, posting on publish with a template per network, the editor panel, the log with re-send, and reconnect notices. **1.1** is re-sharing older items; **1.2** the planner; **1.3** AI captions, images and hooks for other plugins; **Later** is more networks, a group at a time. See [ROADMAP.md](ROADMAP.md) for where each stands today.

## 1. Networks

| Network | How the WordPress plugins connect | How Pollen connects | Verdict | When |
|---|---|---|---|---|
| Facebook Pages | FS Poster: own Meta app, or a browser-cookie method. Revive: its own hosted app, or own keys. | The site owner's own Meta app; Page token kept sealed | **Same** (own app only) | 1.0.0 |
| Facebook groups, personal profile, stories | FS Poster: cookie method only | none | **Not in Pollen**: no open posting API; the cookie method breaks Facebook's terms and breaks often | |
| Instagram (business) | Graph API via a linked Page; FS Poster also has cookie and password logins | Graph API through the Meta app | **Same** (official API only); the cookie and password logins are **Not in Pollen** | Later (group a) |
| X | FS Poster: own keys (OAuth 1.0a) or cookie; Revive: own keys or its hosted proxy | OAuth 2 with PKCE and the owner's app, access renewed before it lapses | **Different**; cookie and hosted proxy **Not in Pollen** | 1.0.0 |
| LinkedIn profile and pages | Own app, OAuth 2 | Own app, OAuth 2; pages need the Community Management product | **Same** | 1.0.0 |
| Telegram | Bot token, chat | Bot token, chat checked when saved | **Same** | 1.0.0 |
| Pinterest | Own app (v5 API) or cookie | Own app | **Same** (official only) | Later (group a) |
| Bluesky | Handle and app password | Handle and app password | **Same** | Later (group a) |
| Threads | Own app | Own app | **Same** | Later (group a) |
| Mastodon | Own app on a server | Token on any server the owner agrees to | **Same** | Later (group a) |
| Reddit | Own app | Own app, with the User-Agent Reddit asks for | **Same** | Later (group b) |
| Discord | Own app plus bot | A channel webhook | **Different**: a webhook needs no bot and no app | Later (group b) |
| Google Business Profile | Own Google app | Own Google app (Google must approve the project) | **Same** | Later (group b) |
| Tumblr | Own app | Own app | **Same** | Later (group b) |
| Blogger | Own Google app | Own Google app | **Same** | Later (group c) |
| Another WordPress site | REST with an application password, or XML-RPC | REST with an application password | **Same**; XML-RPC **Not in Pollen** (old and weak) | Later (group c) |
| VK | Own app | Own app | **Same** | Later (group c) |
| Plurk | Own app (OAuth 1.0a) | Own app; needs OAuth 1.0a signing (written, tested, kept for it) | **Same** | Later (group c) |
| Odnoklassniki | Own app | To be checked: access needs approval by the network | **Can't yet** until its terms are read | |
| Xing | Cookie only | none | **Not in Pollen**: no public API | |
| YouTube community posts | Cookie only | none | **Not in Pollen**: no API for community posts | |
| YouTube Shorts | Own Google app, video upload | none | **Can't yet**: no way to upload a file through Teme's outbound door | |
| TikTok | Own app, video or photo | none | **Can't yet**: photos by address might work; video upload can't | |
| Flickr | Own app (OAuth 1.0a), photo upload | none | **Can't yet**: photo upload | |
| Medium | Integration token | none | **Not in Pollen**: its API is legacy and unmaintained | |
| Truth Social | Pasted token through the vendor's proxy | none | **Not in Pollen**: no API, and it relies on a proxy | |
| Webhook (any URL) | FS Poster and Revive (Pro) send a post to a URL | none | **Teme already does this**: the separate Junction plugin does webhooks | |
| Buffer, WordPress.com | Revive mentions Buffer as legacy | none | **Not in Pollen** | |

The "own app" networks need the site owner to make an app and give Pollen its keys, as both WordPress plugins offer too. Neither of Pollen's models uses a hosted app run by the plugin's author. That is deliberate (section 12).

## 2. Channels (accounts)

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| Connect accounts and pages, then pick which pages, boards or chats to add | **Same** | Name the page, board or chat when adding a channel; if a sign-in reaches several, Pollen lists them and asks which | 1.0.0 |
| Each channel's own sign-in, with OAuth where the network has it | **Same** | The connect and callback addresses, with a random state and PKCE | 1.0.0 |
| Tokens kept safe | **Better** | Sealed with libsodium under a lock key only an administrator sets; never shown, logged or sent to a browser (FINDINGS W2) | 1.0.0 |
| A clear "reconnect" state when a token fails | **Better** | The channel shows "Connect again" with the network's own words, its shares are held, not failed, and go when it is connected | 1.0.0 |
| Renew access that expires | **Same** | Renewed before each send, and by a daily pass for 7 days ahead | 1.0.0 |
| Check a channel is alive (refresh) | **Same** | A check when a token is saved; a "Check every channel" button | 1.0.0 (saved), 1.1 (button) |
| Labels to group channels | **Same** | Words on a channel; a writer can name a label for every channel with it | 1.0.0 |
| Who may post to which channel (role matrix, `can_share` and `full_access`) | **Different** | Two Pollen permissions (share, manage) plus, per channel: everyone who may share, only managers, or the people named | 1.0.0 |
| A proxy per account | **Not in Pollen** | Outbound calls go only through Teme's door, which names the hosts | |
| Switch a channel on or off, rename, delete | **Same** | Row actions in Pollen's area | 1.0.0 |
| "Share new items here" per channel (FS Poster's auto-share switch) | **Same** | A channel lists the content types it shares on publish | 1.0.0 |
| Per-channel category filter for auto-share (include or exclude terms) | **Different** | Rules by category and tag are automations | 1.1 |
| A hosted app for one-click connect (FS Poster's "standard app", Revive's app) | **Not in Pollen** | It would route every site's sign-ins through the plugin author's servers; the owner's own app keeps accounts and tokens on the owner's site | |
| Accounts shared by several plugins | **Can't yet** | Teme has no connected-accounts socket; FINDINGS W1 lists what it needs, so Pollen can move onto it | |

## 3. Posting on publish

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| Share an item when it is published, and when a scheduled item goes live | **Same** | Pollen hears Teme's published event, which also fires when a schedule goes live | 1.0.0 |
| Which content types | **Same** | Set on each channel (default none: the owner chooses) | 1.0.0 |
| An optional delay after publishing | **Same** | "Wait after publishing", in minutes | 1.0.0 |
| Spacing between channels of one post | **Not in Pollen** yet | Shares queue one job each; spacing can be added if asked | |
| Never at page load | **Better** | Only by queued jobs; Pollen's pass runs each minute as the administrator who enabled it | 1.0.0 |
| Retries with backoff | **Better** | FS Poster doesn't retry; Pollen tries a busy network up to three times, waiting 1 and then 5 minutes between tries | 1.0.0 |
| A clear failure state | **Same** | Planned, sending, sent, failed or held, with the network's words | 1.0.0 |
| Re-send a failed share | **Same** | A row action that sends it again as a new share | 1.0.0 |
| Turn sharing off for one item | **Same** | In the editor panel | 1.0.0 |
| Pick channels and edit the text for one item, before it is published | **Same** | The panel's Plan shares action; shares wait for the item to be published | 1.0.0 |
| Share a draft | **Not in Pollen** | Nothing is shared before the item is public | |
| Do not share items that predate the plugin | **Same** | Only items published after Pollen was enabled are heard | 1.0.0 |
| Never share twice on a re-publish | **Same** | One plan per item and channel | 1.0.0 |
| Pause everything | **Better** | One switch, "Pause all sharing" | 1.0.0 |
| Refuse to share on a staging site (Revive) | **Different** | The pause switch; a staging site leaves it on | 1.0.0 |
| Cancel what hasn't gone when the item is unpublished or trashed | **Better** | Done | 1.0.0 |

## 4. Templates

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| A template per network, and per channel | **Same** | Pollen's text, then a text per network (Settings), then a text per channel; the first that is set wins | 1.0.0 |
| Variables: title, excerpt, content, link, author, site, categories, tags | **Same** | `{title}`, `{excerpt}`, `{content}`, `{link}`, `{author}`, `{categories}`, `{tags}`, `{site}`, `{type}`; a word Pollen doesn't know is refused when saved | 1.0.0 |
| Terms as hashtags | **Same** | `{hashtags}`: up to a set number, words joined, letters and digits only | 1.0.0 |
| Custom text | **Same** | It is the template | 1.0.0 |
| Variables for price, product fields, post meta, random IDs, the schedule's ID, the featured image's address | **Not in Pollen** | WooCommerce and meta fields don't exist in Teme; the image and owner aren't readable by plugins (FINDINGS W3) | |
| Spintax `{a|b|c}` | **Not in Pollen** | Varied wording is what AI captions are for | 1.3 |
| Per-post custom messages and rotating variations (Revive, Pro) | **Different** | The panel writes this item's text per network; rotating several variations per item can follow | 1.0.0 (one text), Later (variations) |
| Length per network, cut at a word | **Same** | Each network's limit; links count as the network counts them (X and Mastodon: 23); links are never cut | 1.0.0 |
| Link as a preview card, in the text, in the first comment, or left out | **Same** | Set on a channel; falls back to text where the network has no cards | 1.0.0 |
| First comment | **Same** | Set per network or channel, where the network allows it | 1.3 (the panel and area come first) |
| Link from a custom field, a custom URL template | **Not in Pollen** | The item's own address is the link | |
| URL shorteners (bit.ly, TinyURL, YOURLS, Polr, Shlink, Rebrandly, rviv.ly) | **Not in Pollen** | Calls out to a third party for every share, and breaks links when the service goes; UTM tags cover tracking | |
| UTM tags on links | **Better** | Pattern for source, medium and campaign; `{network}`, `{channel}`, `{slug}`, `{id}`, `{type}`; off or on in one place. FS Poster has query parameters; Revive's free tags are fixed | 1.0.0 |
| Strip or render WordPress shortcodes; page-builder cleanup | **Not in Pollen** | Teme stores content as Tiptap, not shortcodes | |
| Yoast SEO title and description as share text | **Can't yet** | Teme's own SEO and social fields aren't readable by plugins | |

## 5. Images

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| Attach the featured image, or a chosen one, or none | **Different** | An image the writer gives, else the first image in the article (the featured image isn't readable by plugins: W3); "none" is a setting | 1.0.0 |
| Send the image to networks that fetch by address | **Same** | Facebook, LinkedIn cards and Telegram take an address | 1.0.0 |
| Upload image files to networks that need an upload (X, Bluesky, Mastodon, Flickr, TikTok) | **Can't yet** | Teme's outbound door has no file or multipart upload (W5) | |
| Resize and crop per network | **Same** | Copies at each network's best size, served from Pollen's own address | 1.3 |
| Watermark | **Better** | The site's logo in a corner, on copies only; the original is never changed. FS Poster renders on its own servers and counts uses against a plan | 1.3 |
| Multiple images, carousels, video, stories, Reels | **Not in Pollen** yet | Text, link and one image first | Later |
| AI-generated images | **Not in Pollen** | The site's AI connection is asked for words, not pictures | |

## 6. The editor

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| A box in the editor to choose channels and edit text | **Same** | A Pollen panel in the editor's rail | 1.0.0 |
| Preview each network's post | **Different** | The panel can't draw a preview (FINDINGS W9); the Planner's rows show the exact text each network got or will get, and Duplicate re-sends it | 1.0.0 |
| See what was shared and when | **Same** | The panel's status line, and the log | 1.0.0 |
| "Share now" button, instant share on save | **Same** | Plan shares with no time set sends within a minute | 1.0.0 |
| A list column, and a bulk "schedule" action on posts | **Not in Pollen** | Teme has list columns for plugins; this can come with the planner | 1.2 |

## 7. The planner and schedules

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| A calendar of scheduled and sent shares | **Different** | A 30-day chart of what is planned each day, above a table sorted by time (Teme has no calendar block) | 1.2 |
| Schedule a share of any published item for a date and time | **Same** | The Shares record's form | 1.2 |
| A composer for posts that aren't items (an announcement, with an image) | **Same** | The same form with no item | 1.2 |
| Edit, reschedule, delete, bulk delete, filter by status and network | **Same** | Row and bulk actions and filters | 1.0.0 (log), 1.2 (planning) |
| Export the log as CSV | **Not in Pollen** | Pollen's log is read on screen, with filters | |
| A library of content ideas | **Not in Pollen** | A drafts library is writing, not sharing | |

## 8. Re-sharing older items (Revive Social's core; FS Poster's "planners")

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| Pick published items by type, category, tag | **Same** | Automations of the re-share kind | 1.1 |
| Pick by age: older than, newer than | **Same** | Days | 1.1 |
| Exclude items, categories, tags | **Same** | Never these items, never in these terms | 1.1 |
| Keyword include or exclude on title and content | **Not in Pollen** | Categories and tags do the job; Teme has no stored search of words for plugins | |
| Do not repeat an item until a set number of days, or until all have been shared | **Better** | Both choices on one automation; a new round starts when all are done | 1.1 |
| Share at set times or intervals | **Same** | Every 30 minutes up to every week, on chosen days, between chosen hours | 1.1 |
| Several items each time (up to 4 or 5) | **Same** | Up to five | 1.1 |
| Round-robin across channels | **Same** | "Take turns" | 1.1 |
| Order: random, oldest first, least shared first | **Better** | Revive is random only; FS Poster has old to new and random | 1.1 |
| Per-channel schedules | **Same** | One automation names its channels; make several | 1.1 |
| Pause or switch off | **Same** | Automations switch on and off; one pause for everything | 1.0.0 (pause), 1.1 |
| Stop when finished, and say so | **Same** | The automation's row says what its last turn did | 1.1 |
| Re-dating old posts on the site (Revive's "update the published date", RevivePress, Republish Old Posts) | **Not in Pollen** | The owner doesn't want it; it isn't Pollen's job | |
| Staying within a free plan's limits (3 terms, 30 excluded posts, one network) | **Not in Pollen** | Pollen has no tiers | |

## 9. Workflows and hooks

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| "When an item in category X is published, share to channels A and B with template T" | **Same** | An automation of the publish kind | 1.1 |
| Notify people when a share fails (FS Poster workflows: in-app and Telegram) | **Better** | Teme's notices, by email or inbox as each person chose | 1.0.0 |
| Other plugins ask Pollen to share an item (for example Schedulely) | **Different** | A hook other plugins listen to, `pollen:ShareRequests`; plus `pollen:ShareText` and `pollen:ShareSent` | 1.3 |
| A generic webhook channel | **Teme already does this** | The Junction plugin | |
| Revive's WordPress Abilities API (list accounts, schedule, queue) | **Teme already does this** | Teme's management API | |

## 10. AI

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| AI-written share text (OpenAI, Gemini) with a prompt template and fallback | **Different** | The site's own AI connection through its AI door and budget; no key in Pollen; one task, "caption", which sends the item's words as material, never instructions | 1.3 |
| Suggest per network; the writer chooses | **Better** | A suggestion waits in the Planner; nothing is posted without a choice unless an automation says so | 1.3 |
| AI logs | **Teme already does this** | Tools › AI activity | |

## 11. Log, reports and measuring

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| Every share attempt: channel, item, status, the network's reply in short, the link to the post | **Same** | One table of shares is the plan and the log | 1.0.0 |
| Error banners and friendly rewrites of common errors | **Different** | The network's own words, shortened; notices when a channel needs connecting | 1.0.0 |
| Shares per channel and per item | **Same** | Charts on a Reports page | 1.3 |
| Clicks, counted by the plugin on the front end, with bot filtering and cookies | **Not in Pollen** | No reader tracking by Pollen; UTM tags let the site's own statistics tell where readers came from | |
| Likes, reposts and replies fetched from networks | **Not in Pollen** | Per-network insight calls; not needed to share | |
| Importing replies into comments | **Not in Pollen** | Not sharing | |

## 12. Settings, permissions and privacy

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| Who sees the plugin (roles) | **Teme already does this** | Teme's plugin permissions | 1.0.0 |
| Remote services run by the plugin's author (hosted sign-in apps, X proxy, remote cron, remote logs, the vendor's watermark renderer, licence and update checks) | **Not in Pollen** | Pollen calls only the networks the owner connects, through Teme's outbound door, which lists each host | |
| WP-Cron and its health warnings | **Teme already does this** | Teme's scheduler and Tools › Jobs | |
| Housekeeping on uninstall | **Teme already does this** | Remove the plugin's data from the Plugins page | |
| Multisite | **Not in Pollen** | Teme has no multisite | |
| Open Graph and Twitter card tags | **Teme already does this** | Teme's own social fields | |
| Export and import all settings as JSON | **Not in Pollen** yet | | Later |
| Debug logger | **Teme already does this** | Teme's log | |
| Anonymous usage data (Revive) | **Not in Pollen** | No tracking and no calls home | |

## 13. Importing from WordPress

| Feature | Verdict | In Pollen | When |
|---|---|---|---|
| FS Poster's templates and auto-share settings; Revive's re-share plan and filters | **Different** | Through Teme's import socket, as switched-off automations for the owner to check; each says what didn't come across | Later |
| Tokens, cookies and app secrets | **Not in Pollen** | Never imported; channels are connected again. Most were issued to the plugin's hosted app and can't be reused anyway | |
| Planners, schedules, channels, labels, permissions, AI templates, watermark rules | **Can't yet** | The import socket reads WordPress options, declared post types and people's and terms' meta, not a plugin's own tables (FINDINGS W13) | |
