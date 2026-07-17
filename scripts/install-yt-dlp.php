<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * yt-dlp is the downloader this project is built around. Keeping it current matters more than it does for the other
 * vendored binaries: YouTube changes how it detects automated downloads regularly, and an out-of-date yt-dlp is the
 * single most common cause of downloads starting to fail. Treat bumping this version as routine maintenance.
 */
$version = '2026.07.04';

// yt-dlp publishes a universal binary for macOS, and one per architecture for Linux.
[$asset, $sha256] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64' => ['yt-dlp_linux', '6bbb3d314cde4febe36e5fa1d55462e29c974f63444e707871834f6d8cc210ae'],
    'linux-aarch64' => ['yt-dlp_linux_aarch64', 'b6ce97646773070d7a7ffd6bbbdcaecb47c48483909c54c915bf08a7a9b5e0b1'],
    'macos-x86_64', 'macos-aarch64' => ['yt-dlp_macos', '498bd0dae17855c599d371d68ec5bafc439a9d8640e838be25c765a9792f261b'],
    default => throw new RuntimeException("No yt-dlp build available for $platform"),
};

VendoredBinary::install(
    directory: VendoredBinary::BIN_DIR,
    name: 'yt-dlp',
    sha256: $sha256,
    url: "https://github.com/yt-dlp/yt-dlp/releases/download/$version/$asset",
);
