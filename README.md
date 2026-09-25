# Podblender

## Introduction

Podblender lets you add audio clips from around the Web to a custom podcast feed that you can subscribe to in your preferred podcast app. It currently supports:

* **YouTube videos** — the audio track, downloaded and transcoded to MP3
* **Web articles** — the text is extracted and narrated using [Gemini text-to-speech](https://ai.google.dev/gemini-api/docs/speech-generation)
* **YouTube channels and playlists** — subscribe once and new uploads show up in your feed on their own
* **RSS feeds** — subscribe to a text feed and each new item is narrated the same way

You can also add a clip by emailing its link to the feed's own address, which works from the share menu of almost any app. See [Adding clips by email](#adding-clips-by-email).

Why would you want this? It turns out there's a lot of interesting audio content (lectures, interviews, etc.) trapped on video sharing sites. I would prefer to listen to this content in my podcast player, with all its affordances for listening to long audio files: controls to scrub forward/back 30s, dynamic range compression for when speakers are recorded at inconsistent levels, ability to skip silences, etc. Also, even when a video platform (e.g., YouTube) lets you cache videos on mobile devices, it often forces you to cache the video along with the audio track even if you don't intend to watch, wasting space on your device.

## Requirements

* PHP 8.5+ and Composer
* Node and npm
* Redis (queues and cache)
* [ngrok](https://ngrok.com/), if you want to reach your feed from a phone while running locally — see [Listening on your phone](#listening-on-your-phone)

You'll also need API keys, depending on what you want to add to your feeds:

| Key | Needed for |
| --- | --- |
| [Gemini](https://aistudio.google.com/apikey) | Narrating web articles and RSS items |
| [YouTube Data API](https://developers.google.com/youtube/v3/getting-started) | Adding YouTube videos, channels, and playlists |
| [Zyte](https://www.zyte.com/) or [Scrapfly](https://scrapfly.io/) | Optional. Used to fetch an archive.is copy of a paywalled article. See `SCRAPER_PROVIDER` under [Configuration](#configuration) |
| [Resend](https://resend.com/) | Optional. Receiving clips by email |

If YouTube blocks your server's IP address, which is common for addresses in data centers, you'll also need a residential proxy from [Oxylabs](https://oxylabs.io/) or [DataImpulse](https://dataimpulse.com/). See [Configuration](#configuration).

## Installation

> [!NOTE]
> Podblender vendors the executables it needs into `vendor/bin` rather than expecting them on your `PATH`. It supports Linux and macOS, on both x86-64 and arm64.

* Clone the repo
* `composer setup`, which:
  * Runs `composer install`. This also installs the executables Podblender runs into `vendor/bin`: `yt-dlp` and `ffmpeg` to download and transcode audio, plus `deno` and `bgutil-pot`, which yt-dlp needs to answer YouTube's JavaScript challenges and to get a proof-of-origin token. It also installs a small yt-dlp plugin into `vendor/yt-dlp-plugins` that calls `bgutil-pot`. If any of these fail, downloading and storing audio clips won't work
  * Copies `.env.example` to `.env` and fills in `APP_KEY` and the Reverb credentials behind the feed page's live updates
  * Creates the SQLite database and runs the migrations
  * Runs `php artisan storage:link`
  * Runs `npm install` and `npm run build`

  It's safe to rerun: it leaves an existing `.env` and any secrets already set in it alone.
* In the `.env` file, add whichever API keys you need from the table above:
  * `GEMINI_API_KEY`
  * `YOUTUBE_DATA_API_KEY`
  * `ZYTE_API_KEY` or `SCRAPFLY_API_KEY`
* Start the app (see below), open it in a browser, and register an account. To stop anyone else registering on a public server, set `ALLOWED_REGISTRATION_EMAILS`

## Running it

```
php artisan dev
```

The `dev` command starts the web server, the queue worker, the scheduler, Vite, Reverb (which pushes the feed page an update when a clip finishes processing), the log tailer, and an ngrok tunnel, all in one terminal. Run `php artisan dev:list` to see them.

The scheduler is what keeps subscriptions current, so it's included — but note that subscriptions are only swept on even hours, which may well not happen during a short session. If you want to see a subscription pick up new episodes right now, dispatch the sweep by hand:

```
php artisan tinker --execute="App\Jobs\UpdateAllSubscriptions::dispatch()"
```

In production the scheduler is cron's job instead — see [the usual entry](https://laravel.com/docs/13.x/scheduling#running-the-scheduler).

## Usage

**For one-off clips**, create a feed and copy-paste URLs into the UI — a YouTube video or a link to an article. Each one is downloaded (or narrated) in the background; the feed page shows every clip's status and updates itself the moment one is ready.

**To follow something ongoing**, subscribe to it instead: paste a YouTube channel or playlist URL, or the URL of an RSS feed. When you subscribe you can choose:

* **How far back to reach.** By default a new subscription pulls in the last month of episodes, so there's something to listen to straight away rather than an empty feed. You can pick a different date, or ask for everything the source has ever published.
* **Whether to keep following it.** On by default. Turn it off to capture the source as it stands right now and then leave it alone — useful for a playlist you want a snapshot of rather than a running subscription.

Subscriptions are checked every two hours, and new episodes are added to your feed automatically.

Either way, copy the feed's RSS link into your podcast player and enjoy.

### Adding clips by email

Each custom feed has its own email address, shown on the feed page and the dashboard. Email or forward a link to that address and the clip is added to the feed. Podblender takes the first link it finds, looking in the subject, then the body. Each received email is listed on the feed page with its result, and a feed accepts at most 30 emails an hour. If the address leaks, click **New address** on the feed page; mail sent to the old address is then ignored.

Subscription feeds don't have an address.

This requires a [Resend](https://resend.com/) account and a domain, or subdomain, that Resend receives mail for. To set it up:

* In Resend, add the domain and enable receiving for it. Resend lists the MX record to add.
* In Resend, create a webhook for the `email.received` event that points at `https://<your host>/webhooks/resend`. Locally, use your ngrok URL.
* In `.env`, set:
  * `RESEND_KEY`, an API key with full access. Podblender uses it to fetch each email's body
  * `RESEND_WEBHOOK_SECRET`, the webhook's signing secret
  * `RESEND_INBOUND_DOMAIN`, the domain from the first step, e.g. `mail.example.com`

Until `RESEND_INBOUND_DOMAIN` is set, feeds don't show an email address. Until `RESEND_WEBHOOK_SECRET` is set, the webhook route responds with a 404.

### Listening on your phone

Your podcast app needs to be able to reach the feed, which it can't do if the app is only listening on `localhost`. `php artisan dev` starts an ngrok tunnel for exactly this, so the RSS link you copy from the UI is already a public URL that works from your phone.

This is the one optional piece. If ngrok isn't installed, `php artisan dev` simply leaves the tunnel out and everything else runs as normal — you just get a `localhost` feed rather than one your phone can reach.

To turn it on, install the agent — `brew install --cask ngrok` on macOS, or follow [ngrok's Linux instructions](https://ngrok.com/download/linux) for apt and snap. If you install it somewhere that isn't on your `PATH`, point `NGROK_BINARY` in `.env` at it.

Either way, authenticate the agent once with `ngrok config add-authtoken <token>`. If you have a static ngrok domain, set `NGROK_HOST` in `.env` so the URL stays the same between restarts — otherwise your feed's address changes every time you restart and your podcast app loses track of it.

## Configuration

A few things worth knowing about, all settable in `.env`:

| Variable | Default | What it does |
| --- | --- | --- |
| `SUBSCRIPTION_BACKFILL_MONTHS` | `1` | How far back a new subscription reaches by default |
| `MINUTES_BETWEEN_DOWNLOADS` | `2` | How long to leave between downloads. Raise it if downloads start failing; lower it if a new subscription takes too long to fill in |
| `AUDIO_PREVIEW_ENABLED` | `true` | In-browser playback of stored clips on the feed page |
| `NGROK_HOST` | empty | Your static ngrok domain, if you have one |
| `NGROK_BINARY` | `ngrok` | The ngrok agent, if it isn't on your `PATH` |
| `ALLOWED_REGISTRATION_EMAILS` | empty | Comma-separated list of email addresses allowed to register. Empty means anyone can register |
| `GEMINI_TTS_VOICE` | `Aoede` | The [Gemini voice](https://ai.google.dev/gemini-api/docs/speech-generation#voices) that narrates articles |
| `SCRAPER_PROVIDER` | `zyte` | `zyte` (pay as you go) or `scrapfly` (monthly plans). Only the chosen service's key is read. Each paywalled article costs one request |
| `RESIDENTIAL_PROXY_PROVIDER` | `oxylabs` | `oxylabs` or `dataimpulse`. Set that provider's `_USERNAME`, `_PASSWORD`, and optionally `_COUNTRY` (see `.env.example`). Without credentials, downloads that YouTube refuses fail |
| `YTDLP_DIRECT_BLOCK_MINUTES` | `60` | After YouTube refuses a download from your server's address, how long to send every download through the residential proxy |

## License

Podblender is source-available under the [Elastic License 2.0](LICENSE.md). You may run it yourself, including at a business, and modify and redistribute the source. You may not offer it to others as a hosted service.
