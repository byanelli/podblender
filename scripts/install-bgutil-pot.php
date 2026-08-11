<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * YouTube now expects a Proof-of-Origin (PO) Token alongside many of its requests. A token is what distinguishes a
 * request that looks like a real client from one that looks automated, and its absence is the usual cause of
 * "Sign in to confirm you're not a bot". Tokens are bound to a video ID and expire, so they have to be minted per
 * download rather than extracted once by hand.
 *
 * yt-dlp doesn't mint tokens itself; it delegates to a PO token provider plugin. We use the Rust port of
 * bgutil-ytdlp-pot-provider, because unlike the original TypeScript implementation it ships a standalone executable
 * and so needs neither Node nor Docker. See https://github.com/yt-dlp/yt-dlp/wiki/PO-Token-Guide.
 *
 * There are two halves to install, and they are versioned together:
 *
 *   1. `bgutil-pot`, the executable that mints tokens, into vendor/bin.
 *   2. A small pure-Python yt-dlp plugin that calls it, into vendor/yt-dlp-plugins. yt-dlp discovers this via the
 *      `--plugin-dirs` argument passed by App\Apis\YtDlp\Client.
 */
$version = '0.8.1';

$baseUrl = "https://github.com/jim60105/bgutil-ytdlp-pot-provider-rs/releases/download/v$version";

[$asset, $sha256] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64'  => ['bgutil-pot-linux-x86_64', 'e7c264a574fa2705b6e5dc62283a8a4e80130f27b9d7e9df44e6b09aa6151a87'],
    'linux-aarch64' => ['bgutil-pot-linux-aarch64', '4f4a1f681dba45e695e1c14d314517da180a1fd374afd09d634fd80ef6d0284b'],
    'macos-x86_64'  => ['bgutil-pot-macos-x86_64', '0391175fa938c7fabbb8b40a40bd43182ef75af97e1dd3fab56eb23b4ac3e113'],
    'macos-aarch64' => ['bgutil-pot-macos-aarch64', '34b83baf0a557fecaa6d67a8177e53e169c2ccf987182883a4bae289a7176883'],
    default         => throw new RuntimeException("No bgutil-pot build available for $platform"),
};

VendoredBinary::install(
    directory: VendoredBinary::BIN_DIR,
    name: 'bgutil-pot',
    sha256: $sha256,
    url: "$baseUrl/$asset",
);

$pluginUrl = "$baseUrl/bgutil-ytdlp-pot-provider-rs.zip";

/**
 * The plugin ships two providers: one that shells out to the executable above, and one that calls a long-running HTTP
 * server. We only install the first. Installing the second as well would mean every download first tries, and waits
 * on, a connection to a server on localhost that we deliberately don't run, logging a warning each time.
 */
foreach ([
    'getpot_bgutil.py'     => '54d0fc3a6bed8fa2c5012db3bfb9c80e19e7d1d7a7e05efb422f348f2bcbb0b2',
    'getpot_bgutil_cli.py' => '3e6d1835eb58d66d481e7e49620f97d575bd11ded7db8035c1719cffe74c2448',
] as $file => $fileSha256) {
    VendoredBinary::installFromZip(
        directory: './vendor/yt-dlp-plugins/bgutil-ytdlp-pot-provider',
        member: "yt_dlp_plugins/extractor/$file",
        sha256: $fileSha256,
        url: $pluginUrl,
    );
}
