<?php

/**
 * Helpers shared by the scripts that vendor statically-linked binaries into `vendor/bin`: yt-dlp, ffmpeg, deno and
 * bgutil-pot. Composer runs those scripts from the project root, so the paths here are relative to it.
 *
 * Every file is pinned to an exact version and SHA-256. A pinned hash means `composer install` is reproducible and a
 * tampered-with release can't silently replace a binary we then execute. The tradeoff is that bumping a version is a
 * manual edit of both the version and the hashes; see "Updating the vendored binaries" in the README, which documents
 * how to obtain the hashes for a new release.
 */
final class VendoredBinary
{
    public const BIN_DIR = './vendor/bin';

    /**
     * Paths of the zip archives downloaded so far, keyed by URL, so that pulling several files out of one archive
     * (the yt-dlp plugin) doesn't download it once per file.
     */
    private static array $downloadedZips = [];

    /**
     * The current OS and CPU as a `<os>-<arch>` string, e.g. `linux-x86_64` or `macos-aarch64`. Each install script
     * maps this to the download its upstream publishes, because no two of them name their assets the same way.
     */
    public static function platform(): string
    {
        $os = match (true) {
            str_contains($uname = php_uname('s'), 'Darwin') => 'macos',
            str_contains($uname, 'Linux') => 'linux',
            default => throw new RuntimeException("Unsupported operating system: $uname"),
        };

        $arch = match ($machine = php_uname('m')) {
            'x86_64', 'amd64' => 'x86_64',
            'arm64', 'aarch64' => 'aarch64',
            default => throw new RuntimeException("Unsupported architecture: $machine"),
        };

        return "$os-$arch";
    }

    private static function matchesHash(string $path, string $sha256): bool
    {
        return file_exists($path) && hash_file('sha256', $path) === $sha256;
    }

    private static function download(string $url, string $path): void
    {
        // -f makes curl exit non-zero on an HTTP error rather than cheerfully writing the error page to disk in place
        // of the binary we asked for, and -L follows the redirect from a GitHub release to its storage host.
        $command = sprintf('curl -fsSL %s --output %s', escapeshellarg($url), escapeshellarg($path));

        // curl's exit code is the only reliable signal here. shell_exec() returns the command's *output*, which is
        // empty whether it succeeded or failed, so it can't tell us anything.
        exec($command, $output, $exitCode);

        ($exitCode === 0 && file_exists($path))
            || throw new RuntimeException("Error downloading $url (curl exited with status $exitCode)");
    }

    private static function makeExecutable(string $path): void
    {
        if (! is_executable($path)) {
            chmod($path, 0755);
            is_executable($path) || throw new RuntimeException("Error making $path executable");
        }
    }

    private static function verify(string $path, string $sha256, string $url): void
    {
        if (! self::matchesHash($path, $sha256)) {
            $actual = file_exists($path) ? hash_file('sha256', $path) : 'file missing';

            throw new RuntimeException(
                "Checksum mismatch for $path downloaded from $url.\nExpected: $sha256\nActual:   $actual"
            );
        }
    }

    /**
     * Ensure `$directory/$name` exists, is executable and has the given SHA-256, downloading it from $url if not.
     */
    public static function install(string $directory, string $name, string $sha256, string $url): void
    {
        $path = "$directory/$name";

        if (self::matchesHash($path, $sha256)) {
            self::makeExecutable($path);

            return;
        }

        if (file_exists($path)) {
            echo "$name is out of date or invalid. Deleting and re-downloading\n";
            unlink($path) || throw new RuntimeException("Error removing $path");
        }

        is_dir($directory) || mkdir($directory, 0755, true);

        echo "Downloading $name from $url\n";
        self::download($url, $path);
        self::verify($path, $sha256, $url);
        self::makeExecutable($path);

        echo "Successfully installed $name at $path\n";
    }

    /**
     * Ensure `$directory/$member` exists with the given SHA-256, extracting it from the zip archive at $url if not.
     * $member is the path of the file *inside* the archive, and is preserved on extraction: pulling
     * `yt_dlp_plugins/extractor/foo.py` out of an archive writes it to `$directory/yt_dlp_plugins/extractor/foo.py`.
     */
    public static function installFromZip(
        string $directory,
        string $member,
        string $sha256,
        string $url,
        bool $executable = false,
    ): void {
        $path = "$directory/$member";

        if (self::matchesHash($path, $sha256)) {
            $executable && self::makeExecutable($path);

            return;
        }

        if (file_exists($path)) {
            echo "$member is out of date or invalid. Deleting and re-extracting\n";
            unlink($path) || throw new RuntimeException("Error removing $path");
        }

        is_dir($directory) || mkdir($directory, 0755, true);

        if (! isset(self::$downloadedZips[$url])) {
            $zipPath = tempnam(sys_get_temp_dir(), 'vendored-binary-').'.zip';

            echo "Downloading ".basename($url)." from $url\n";
            self::download($url, $zipPath);

            self::$downloadedZips[$url] = $zipPath;

            // The archives are large and we only want the files we extract, so don't leave them on disk afterwards.
            register_shutdown_function(fn () => file_exists($zipPath) && unlink($zipPath));
        }

        $zip = new ZipArchive();
        ($zip->open(self::$downloadedZips[$url]) === true) || throw new RuntimeException("Error opening archive from $url");

        $zip->extractTo($directory, [$member]);
        $zip->close();

        file_exists($path) || throw new RuntimeException("Error extracting $member from $url");

        self::verify($path, $sha256, $url);
        $executable && self::makeExecutable($path);

        echo "Successfully installed $member at $path\n";
    }
}
