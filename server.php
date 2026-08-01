<?php

// Router for PHP's built-in dev server: `php -S 127.0.0.1:8000 -t public server.php`.
//
// The built-in server serves /storage/* (audio files, via the public symlink)
// as static files with NO HTTP Range support. Podcast apps require Range to
// stream and seek episodes — without it they force a full download before
// playback. Requests under /storage/ are answered here with Range support
// (Accept-Ranges + 206 Partial Content); everything else follows Laravel's
// normal routing. Production (nginx/apache) handles Range natively, so this
// file is dev-only.

$publicPath = realpath(__DIR__.'/public');
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (str_starts_with($uri, '/storage/')) {
    // Block traversal; the file is under public/storage -> storage/app/public.
    if (str_contains($uri, '..')) {
        http_response_code(404);

        return true;
    }

    $file = $publicPath.$uri;

    if (! is_file($file) || ! is_readable($file)) {
        http_response_code(404);

        return true;
    }

    $size = filesize($file);
    $mime = str_ends_with($file, '.mp3') ? 'audio/mpeg' : 'application/octet-stream';
    $start = 0;
    $end = $size - 1;
    $status = 200;

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches) === 1) {
        $suffix = $matches[1] === '';
        $start = $suffix ? null : (int) $matches[1];
        $end = $matches[2] === '' ? null : (int) $matches[2];

        if ($suffix) {
            // "bytes=-N": the last N bytes.
            $start = max(0, $size - $end);
            $end = $size - 1;
        } else {
            $end = ($end === null || $end >= $size) ? $size - 1 : $end;
        }

        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */'.$size);

            return true;
        }

        $status = 206;
    }

    header('Accept-Ranges: bytes');
    header('Content-Type: '.$mime);
    header('Content-Length: '.($end - $start + 1));

    if ($status === 206) {
        http_response_code(206);
        header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        $handle = fopen($file, 'rb');
        fseek($handle, $start);
        fpassthru($handle);
        fclose($handle);
    }

    return true;
}

// Let the built-in server serve real static assets (build files, favicons, ...).
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
