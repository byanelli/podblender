<?php

require_once __DIR__.'/lib/vendored-binary.php';

/**
 * As of yt-dlp 2025.11.12, YouTube's JavaScript challenges are beyond yt-dlp's built-in interpreter, and an external
 * JavaScript runtime is required for full YouTube support. Without one, the available formats are limited and anything
 * requiring a signed player URL tends to fail.
 *
 * yt-dlp accepts several runtimes but only enables Deno by default, because Deno sandboxes filesystem and network
 * access unless explicitly granted. It also ships as a single portable executable, which is what lets us vendor it the
 * same way we vendor ffmpeg. See https://github.com/yt-dlp/yt-dlp/wiki/EJS.
 */
$version = '2.9.3';

[$target, $sha256] = match ($platform = VendoredBinary::platform()) {
    'linux-x86_64' => ['x86_64-unknown-linux-gnu', '8570c9cdebe936ba744e12a6d329e0a17ea505b4e5f89b654473a2efc2d2e3ba'],
    'linux-aarch64' => ['aarch64-unknown-linux-gnu', 'f398f4b98c74634e56b56f8229bef5daeca02107f9dcd036ff99dbf28bc1b07e'],
    'macos-x86_64' => ['x86_64-apple-darwin', '480b74c4fe7c316f7719a91ceb57e57c50798f1a0c470e00674b683e9e1a76d4'],
    'macos-aarch64' => ['aarch64-apple-darwin', '80c83cdfb20289f8818a71220b570df363f6f0ba93580c29c70f08ab14e93568'],
    default => throw new RuntimeException("No Deno build available for $platform"),
};

VendoredBinary::installFromZip(
    directory: VendoredBinary::BIN_DIR,
    member: 'deno',
    sha256: $sha256,
    url: "https://github.com/denoland/deno/releases/download/v$version/deno-$target.zip",
    executable: true,
);
