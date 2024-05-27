<?php

$downloadUrl = "https://github.com/yt-dlp/yt-dlp/releases/download/2023.12.30/yt-dlp_linux";
$correctSha256 = "0f606eab88c629884e673ae69355fbd5d0caf035f299a3f32e104bbf4ff90063";
$downloadedFilePath = "./vendor/bin/yt-dlp";

$downloadedFileExists = fn() => file_exists($downloadedFilePath);

$downloadedFileSha256 = $downloadedFileExists()
    ? hash('sha256', file_get_contents($downloadedFilePath))
    : null;

if ($downloadedFileExists() && ($downloadedFileSha256 != $correctSha256)) {
    echo "yt-dlp is invalid. Deleting and re-downloading\n";
    unlink($downloadedFilePath) || throw new \RuntimeException('Error removing file');
}

if (!$downloadedFileExists()) {
    echo "Downloading yt-dlp from $downloadUrl\n";
    $result = shell_exec("curl -L $downloadUrl --output $downloadedFilePath");
    ($result === false || !$downloadedFileExists()) && throw new \RuntimeException('Error downloading file');
    echo "Successfully downloaded yt-dlp from $downloadUrl\n";
}

if (!is_executable($downloadedFilePath)) {
    echo "Making yt-dlp executable at: $downloadedFilePath\n";
    chmod($downloadedFilePath, 0755);
    is_executable($downloadedFilePath) || throw new \RuntimeException('Error making yt-dlp executable');
}
