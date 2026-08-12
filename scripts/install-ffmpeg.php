<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * ffmpeg does the transcoding to mp3 once yt-dlp has fetched the audio.
 */

// ffbinaries covers every platform here except Apple Silicon. Its Linux arm64 asset is named "linux-arm-64", which
// reads like 32-bit ARM on a 64-bit OS but is a statically linked aarch64 build (the 32-bit ones are "armel"/"armhf").
// Its macOS builds, though, are repackaged from evermeet.cx, which is x86_64-only by design — so on an M-series Mac
// that binary runs under Rosetta, which costs roughly 5x on a transcode. ffmpeg-static publishes a real arm64 Mach-O
// linking only system frameworks, so Apple Silicon takes its ffmpeg from there instead. Two publishers is the price of
// a native binary on the machine most of this gets developed on.
$ffbinariesVersion = '6.1';
$ffmpegStaticVersion = 'b6.1.1';

// [url, sha256 of the installed file, whether the download is a zip to extract "ffmpeg" from]
[$url, $sha256, $zipped] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64'  => [
        "https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v$ffbinariesVersion/ffmpeg-$ffbinariesVersion-linux-64.zip",
        'a0082b064cc83f5606554fa2cc5b07194ade90f6669b1fcfd6499b29861ca403',
        true,
    ],
    'linux-aarch64' => [
        "https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v$ffbinariesVersion/ffmpeg-$ffbinariesVersion-linux-arm-64.zip",
        '593df241f0e9f472e3e3fd2cbe12186b2509dceef82f02aa99e0053acec5dbd2',
        true,
    ],
    'macos-x86_64'  => [
        "https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v$ffbinariesVersion/ffmpeg-$ffbinariesVersion-macos-64.zip",
        'ca8945e5eef946a246d29c943b21f10db345a2ef050dd7ea1c77f877277dc2fa',
        true,
    ],
    'macos-aarch64' => [
        "https://github.com/eugeneware/ffmpeg-static/releases/download/$ffmpegStaticVersion/ffmpeg-darwin-arm64",
        'a90e3db6a3fd35f6074b013f948b1aa45b31c6375489d39e572bea3f18336584',
        false,
    ],
    default         => throw new RuntimeException("No ffmpeg build available for $platform"),
};

$zipped
    ? VendoredBinary::installFromZip(
        directory: VendoredBinary::BIN_DIR,
        member: 'ffmpeg',
        sha256: $sha256,
        url: $url,
        executable: true,
    )
    : VendoredBinary::install(
        directory: VendoredBinary::BIN_DIR,
        name: 'ffmpeg',
        sha256: $sha256,
        url: $url,
    );
