<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * yt-dlp is the downloader this project is built around. Keeping it current matters more than it does for the other
 * vendored binaries: YouTube changes how it detects automated downloads regularly, and an out-of-date yt-dlp is the
 * single most common cause of downloads starting to fail. Treat bumping this version as routine maintenance.
 */
$version = '2026.08.19';

// yt-dlp publishes a universal binary for macOS, and one per architecture for Linux.
[$asset, $sha256] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64'                  => ['yt-dlp_linux', '58162f9bfdc27458ea47bfcb311cf47028f17d8154a8bf7d689861d46399230a'],
    'linux-aarch64'                 => ['yt-dlp_linux_aarch64', 'b16e4dab368a816cd05d477d698a605a6ae87ccee1c8ffd38fa21d7254141fcc'],
    'macos-x86_64', 'macos-aarch64' => ['yt-dlp_macos', '0f192b7ec147ab6288885d6351d9ab67367640029b4377576ef46dd79cf7b202'],
    default                         => throw new RuntimeException("No yt-dlp build available for $platform"),
};

VendoredBinary::install(
    directory: VendoredBinary::BIN_DIR,
    name: 'yt-dlp',
    sha256: $sha256,
    url: "https://github.com/yt-dlp/yt-dlp/releases/download/$version/$asset",
);
