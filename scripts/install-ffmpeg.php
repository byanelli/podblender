<?php

$downloadZipUrl = 'https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v6.1/ffmpeg-6.1-linux-32.zip';
$downloadedZipPath = './vendor/bin/ffmpeg.zip';

$unzippedFileParent = './vendor/bin';
$unzippedFileName = 'ffmpeg';
$unzippedFilePath = $unzippedFileParent.'/'.$unzippedFileName;
$unzippedFileCorrectSha256 = 'fcf32e59d732da38f44d3c7c2a67a89d695c1e43efa95d5bef87834910277609';

$downloadedZipExists = fn () => file_exists($downloadedZipPath);
$unzippedFileExists = fn () => file_exists($unzippedFilePath);

$unzippedFileSha256 = $unzippedFileExists()
    ? hash('sha256', file_get_contents($unzippedFilePath))
    : null;

if ($unzippedFileExists() && ($unzippedFileSha256 != $unzippedFileCorrectSha256)) {
    echo "ffmpeg is invalid. Deleting and re-downloading\n";
    unlink($unzippedFilePath) || throw new \RuntimeException('Error removing file');
}

if (! $unzippedFileExists()) {
    echo "Downloading ffmpeg.zip from $downloadZipUrl\n";

    $result = shell_exec("curl -L $downloadZipUrl --output $downloadedZipPath");
    ($result === false || ! $downloadedZipExists()) && throw new \RuntimeException('Error downloading file');

    $zip = new ZipArchive();
    ($zip->open($downloadedZipPath) === true) || throw new \RuntimeException('Error opening zip archive');

    $zip->extractTo($unzippedFileParent, [$unzippedFileName]);

    file_exists($unzippedFilePath) || throw new \RuntimeException('Error unzipping file');

    echo "Successfully downloaded ffmpeg from $downloadZipUrl\n";
}

if (! is_executable($unzippedFilePath)) {
    echo "Making ffmpeg executable at: $unzippedFilePath\n";
    chmod($unzippedFilePath, 0755);
    is_executable($unzippedFilePath) || throw new \RuntimeException('Error making ffmpeg executable');
}
