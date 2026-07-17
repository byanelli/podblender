<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * ffmpeg does the transcoding to mp3 once yt-dlp has fetched the audio.
 */
$version = '6.1';

// ffbinaries publishes one x86_64 build per OS. On Apple Silicon it runs under Rosetta, which is slower than a native
// build but works; there is no arm64 Linux build, so that combination has to be installed some other way.
[$asset, $sha256] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64' => ['ffmpeg-6.1-linux-64.zip', 'ffcd56ce5ef50c4d36d675b0ee80674f5a0869f94746460ff5d058a33cbd3128'],
    'macos-x86_64', 'macos-aarch64' => ['ffmpeg-6.1-macos-64.zip', 'ca8945e5eef946a246d29c943b21f10db345a2ef050dd7ea1c77f877277dc2fa'],
    default => throw new RuntimeException("No ffmpeg build available for $platform"),
};

VendoredBinary::installFromZip(
    directory: VendoredBinary::BIN_DIR,
    member: 'ffmpeg',
    sha256: $sha256,
    url: "https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v$version/$asset",
    executable: true,
);
