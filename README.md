# Podblender

## Introduction

Podblender lets you add audio clips from around the Web to a custom podcast feed that you can subscribe to in your preferred podcast app. It currently supports:

* YouTube videos
* Web articles (text extraction using [Apify](https://apify.com/lukaskrivka/article-extractor-smart), text-to-speech conversion using [OpenAI Whisper](https://platform.openai.com/docs/guides/speech-to-text))

Why would you want this? It turns out there's a lot of interesting audio content (lectures, interviews, etc.) trapped on video sharing sites. I would prefer to listen to this content in my podcast player, with all its affordances for listening to long audio files: controls to scrub forward/back 30s, dynamic range compression for when speakers are recorded at inconsistent levels, ability to skip silences, etc. Also, even when a video platform (e.g., YouTube) lets you cache videos on mobile devices, it often forces you to cache the video along with the audio track even if you don't intend to watch, wasting space on your device. 

## Motivation / a note on the code

This is a side project for personal use and to refresh my Laravel skills. The code style is slightly experimental—my goal was to see how far I could push Laravel in the direction of type-safety, avoidance of globals/facades, and lack of "magic" in general. I wouldn't recommend this style for every project. Still, I enjoyed this experiment and am considering writing more about it in the future. It turns out you can write Laravel in such a way that virtually all code is IDE-inspectable, auditable, and easy to refactor without the use of any vendor-specific plugins. (Note: this experiment does not extend to the [Breeze](https://laravel.com/docs/11.x/starter-kits#laravel-breeze) controllers, routes, components, etc., which are still written in more of a Laravel house style.)

## Installation

> [!NOTE]
> Podblender vendors the executables it needs into `vendor/bin` rather than expecting them on your `PATH`. It supports Linux and macOS on x86-64, and macOS on Apple Silicon. On arm64 Linux everything but ffmpeg will install, and ffmpeg has to be provided some other way.

* Clone the repo
* `composer install`
  * This installs four executables into `vendor/bin`: `yt-dlp` and `ffmpeg` to download and transcode audio, and `deno` and `bgutil-pot`, which exist only to get YouTube downloads past bot detection (see below). It also installs a small yt-dlp plugin into `vendor/yt-dlp-plugins`. If any of these fail, downloading and storing audio clips won't work
* In the `.env` file:
  * Add your [OpenAI API key](https://help.openai.com/en/articles/4936850-where-do-i-find-my-openai-api-key)
  * Add your [Apify API token](https://docs.apify.com/platform/integrations/api)
* Run all of the following to run the app locally:
  * `npm run dev`
  * `php artisan serve`
  * `php artisan queue:work`
  * `php artisan reverb:start`

## Usage

Create a feed and copy-paste URLs into the UI to add clips. Copy the RSS link into your podcast player and enjoy. Note: if you're running locally and not on a public web server, you may have to use a service like [ngrok](https://ngrok.com/) to get your phone connected to the RSS feed.

## Downloading from YouTube without getting blocked

Downloading one video from YouTube is easy. Downloading a channel's worth, on a schedule, forever, is the actual engineering problem this project has, so it's worth writing down what the moving parts are for.

YouTube decides whether to serve a request based on roughly three things:

**1. Whether the request carries a proof-of-origin (PO) token.** This is the big one, and its absence is what produces "Sign in to confirm you're not a bot". Tokens are minted per video by `bgutil-pot`, via a yt-dlp plugin, and yt-dlp additionally needs a real JavaScript runtime — Deno — to solve the challenges guarding them. Both are installed by `composer install` and wired up in `App\Apis\YtDlp\Client`. Note the failure mode: without them yt-dlp mostly *degrades* rather than failing loudly, so this stays working right up until it doesn't.

**2. The reputation of the IP the request comes from.** Datacenter IP ranges are flagged more or less on sight, and *every commercial VPN endpoint is a datacenter IP*, so routing yt-dlp through a VPN makes things worse rather than better. Residential IPs are treated as people. So the client tries the host's own connection first and only falls back to a residential proxy (Oxylabs) if that fails.

Note that a residential pool hands out a different address on *every request* unless told otherwise, and a YouTube download can't survive that: YouTube signs the media URL against the address that asked for it, so fetching the metadata and the media from two addresses is refused, with a 403 that looks just like being blocked. `App\Proxies\OxylabsResidentialProxyConfig` pins one address per download, which is what `ProxyConfig::getUrlForDownload()` exists to express.

The practical consequence is that **where you run this matters more than any setting in it**. Running it on a home connection is ideal. If you want it on a VPS, the cheapest good option is to route its traffic out through your home connection with a WireGuard or Tailscale exit node, which gets you a residential IP without paying anyone for one.

**3. Whether requests arrive in a burst.** Subscribing to a channel can create a lot of clips at once, and Horizon is happy to run many workers, so `App\Jobs\DownloadAndStoreAudioClip` deliberately downloads one clip at a time with a gap between each (`config/downloads.php`). A podcast feed is read minutes or hours after it's published, so a slow trickle costs nothing.

If downloads start failing, in order: update yt-dlp, raise `MINUTES_BETWEEN_DOWNLOADS`, then look at the IP.

### Updating the vendored binaries

Each binary is pinned to an exact version and SHA-256 in `scripts/`, so `composer install` is reproducible and a tampered-with release can't silently replace something we execute. The tradeoff is that upgrading is a manual edit.

**Updating yt-dlp is routine maintenance, not an optional chore.** YouTube changes its defenses regularly, and a stale yt-dlp is the single most common cause of downloads that used to work and no longer do. To bump it, edit `$version` in `scripts/install-yt-dlp.php` and update the hashes, which yt-dlp publishes for each release:

```
curl -sL https://github.com/yt-dlp/yt-dlp/releases/download/<version>/SHA2-256SUMS
```

Deno and ffmpeg publish per-asset checksums, but they're for the zip archives rather than the binary inside, and we pin the binary. `bgutil-pot` publishes no checksums at all. For those, download the asset, extract it if necessary, and hash what you'd end up with:

```
shasum -a 256 <file>
```

Then run `composer install`: a changed pin makes the installer delete and re-download the file, and a hash that doesn't match what arrives is a hard error.
