<?php

$uname = php_uname();
$version = '2024.12.23';

[$downloadUrl, $correctSha256] = (function () use ($uname, $version) {
    if (str_contains($uname, 'Darwin')) {
        return [
            "https://github.com/yt-dlp/yt-dlp/releases/download/$version/yt-dlp",
            'eb5fef5807129b445d20a557cf57b5a9eaafb84d9f575bfcd51c5598cd70a133',
        ];
    } else if (str_contains($uname, 'Linux')) {
        return [
            "https://github.com/yt-dlp/yt-dlp/releases/download/$version/yt-dlp_linux",
            '50a1799719fc69baf387e5bce8e38bd9aef2d177e0569d666151a4f9e40a461d',
        ];
    } else {
        throw new RuntimeException('Unsupported operating system');
    }
})();

$downloadedFilePath = './vendor/bin/yt-dlp';

$downloadedFileExists = fn () => file_exists($downloadedFilePath);

$downloadedFileSha256 = $downloadedFileExists()
    ? hash('sha256', file_get_contents($downloadedFilePath))
    : null;

if ($downloadedFileExists() && ($downloadedFileSha256 != $correctSha256)) {
    echo "yt-dlp is invalid. Deleting and re-downloading\n";
    unlink($downloadedFilePath) || throw new \RuntimeException('Error removing file');
}

if (! $downloadedFileExists()) {
    echo "Downloading yt-dlp from $downloadUrl\n";
    $result = shell_exec("curl -L $downloadUrl --output $downloadedFilePath");
    ($result === false || ! $downloadedFileExists()) && throw new \RuntimeException('Error downloading file');
    echo "Successfully downloaded yt-dlp from $downloadUrl\n";
}

if (! is_executable($downloadedFilePath)) {
    echo "Making yt-dlp executable at: $downloadedFilePath\n";
    chmod($downloadedFilePath, 0755);
    is_executable($downloadedFilePath) || throw new \RuntimeException('Error making yt-dlp executable');
}
