<?php

$uname = php_uname();

[$downloadZipUrl, $unzippedFileCorrectSha256] = (function () use ($uname) {
    $base = 'https://github.com/ffbinaries/ffbinaries-prebuilt/releases/download/v6.1/';

    if (str_contains($uname, 'Darwin')) {
        return [
            $base.'ffmpeg-6.1-macos-64.zip',
            '8bb4a27f5fd02f3dd9a5e75c9eddf6ace1d50a08929ee0d20bbf17eb467fb711',
        ];
    } else if (str_contains($uname, 'Linux')) {
        return [
            $base.'ffmpeg-6.1-linux-64.zip',
            'ffcd56ce5ef50c4d36d675b0ee80674f5a0869f94746460ff5d058a33cbd3128',
        ];
    } else {
        throw new RuntimeException('Unsupported operating system');
    }
})();
$downloadedZipPath = './vendor/bin/ffmpeg.zip';

$unzippedFileParent = './vendor/bin';
$unzippedFileName = 'ffmpeg';
$unzippedFilePath = $unzippedFileParent.'/'.$unzippedFileName;

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
