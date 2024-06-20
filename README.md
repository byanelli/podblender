# Podblender

## Introduction

Podblender lets you add audio clips from around the Web to a custom podcast feed that you can subscribe to in your preferred podcast app. It currently supports:

* YouTube videos
* Twitch VOD and clips
* SoundCloud tracks
* Web articles (text extraction using [Apify](https://apify.com/lukaskrivka/article-extractor-smart), text-to-speech conversion using [OpenAI Whisper](https://platform.openai.com/docs/guides/speech-to-text))

Why would you want this? It turns out there's a lot of interesting audio content (lectures, interviews, etc.) trapped on video sharing sites. I would prefer to listen to this content in my podcast player, with all its affordances for listening to long audio files: controls to scrub forward/back 30s, dynamic range compression for when speakers are recorded at inconsistent levels, ability to skip silences, etc. Also, even when a video platform (e.g., YouTube) lets you cache videos on mobile devices, it often forces you to cache the video along with the audio track even if you don't intend to watch, wasting space on your device. 

## Motivation / a note on the code

This is a side project for personal use and to refresh my Laravel skills. The code style is slightly experimental—my goal was to see how far I could push Laravel in the direction of type-safety, avoidance of globals/facades, and lack of "magic" in general. I wouldn't recommend this style for every project. Still, I enjoyed this experiment and am considering writing more about it in the future. It turns out you can write Laravel in such a way that virtually all code is IDE-inspectable, auditable, and easy to refactor without the use of any vendor-specific plugins. (Note: this experiment does not extend to the [Breeze](https://laravel.com/docs/11.x/starter-kits#laravel-breeze) controllers, routes, components, etc., which are still written in more of a Laravel house style.)

## Installation

> [!NOTE]
> Podblender uses yt-dlp and ffmpeg to download, transcode, and extract metadata from audio. Currently it only supports Linux on x86. macOS Apple Silicon support coming soon.

* Clone the repo
* `composer install`
  * `yt-dlp` and `ffmpeg` should automatically be installed during this step. If any of these installations fail, downloading and storing audio clips may not work
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
