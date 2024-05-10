<?php

$downloadUrl = "https://github.com/yt-dlp/yt-dlp/releases/download/2023.12.30/yt-dlp_linux";
$correctSha256 = "0f606eab88c629884e673ae69355fbd5d0caf035f299a3f32e104bbf4ff90063";
$downloadedFilePath = "./vendor/bin/youtube-dl";

$downloadedFileExists = file_exists($downloadedFilePath);

$downloadedFileSha256 = $downloadedFileExists
    ? hash('sha256', file_get_contents($downloadedFilePath))
    : null;

if ($downloadedFileExists && ($downloadedFileSha256 == $correctSha256)) {
    echo "Already downloaded youtube-dl from $downloadUrl\n";
    exit(0);
} else {
    echo "Downloading youtube-dl from $downloadUrl\n";
    shell_exec("curl -L $downloadUrl --output $downloadedFilePath");
}

if (hash('sha256', file_get_contents($downloadedFilePath)) != $correctSha256) {
    echo "Error downloading youtube-dl from $downloadUrl\n";
    exit(1);
} else {
    echo "Successfully downloaded youtube-dl from $downloadUrl\n";
    exit(0);
}
