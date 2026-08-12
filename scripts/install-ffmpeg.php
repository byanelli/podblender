<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * ffmpeg does the transcoding to mp3 once yt-dlp has fetched the audio.
 */
$version = '6.1';

// ffbinaries publishes both architectures for Linux but only x86_64 for macOS, where it runs under Rosetta on Apple
// Silicon: slower than a native build, but it works. Their Linux arm64 asset is named "linux-arm-64", which reads like
// 32-bit ARM on a 64-bit OS but is in fact a statically linked aarch64 build (their 32-bit assets are the "armel"/
// "armhf" ones).
[$asset, $sha256] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64'                  => ['ffmpeg-6.1-linux-64.zip', 'a0082b064cc83f5606554fa2cc5b07194ade90f6669b1fcfd6499b29861ca403'],
    'linux-aarch64'                 => ['ffmpeg-6.1-linux-arm-64.zip', '593df241f0e9f472e3e3fd2cbe12186b2509dceef82f02aa99e0053acec5dbd2'],
    'macos-x86_64', 'macos-aarch64' => ['ffmpeg-6.1-macos-64.zip', 'ca8945e5eef946a246d29c943b21f10db345a2ef050dd7ea1c77f877277dc2fa'],
    default                         => throw new RuntimeException("No ffmpeg build available for $platform"),
};

VendoredBinary::installFromZip(
    directory: VendoredBinary::BIN_DIR,
    member: 'ffmpeg',
    sha256: $sha256,
    url: "https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v$version/$asset",
    executable: true,
);
