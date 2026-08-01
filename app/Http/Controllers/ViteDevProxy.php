<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Forwards requests for Vite dev-server assets back to the Vite server itself.
 *
 * In development the frontend is served as unbundled modules straight from
 * Vite. Laravel normally points the browser at http://127.0.0.1:5173 for those,
 * which works on the machine running Vite but not through a tunnel like ngrok
 * (a phone can't reach your loopback). Routing the requests through this same
 * app instead keeps everything same-origin, so a single public URL serves both
 * the site and its JavaScript.
 *
 * Only reachable while the Vite dev server is running, because routes/web.php
 * registers it only when public/hot exists (the same gate @vite uses).
 */
final readonly class ViteDevProxy
{
    public function __construct(private string $viteUrl = 'http://127.0.0.1:5173') {}

    public function __invoke(Request $request, string $path): Response
    {
        $url = $this->viteUrl.'/'.ltrim($path, '/');

        if ($query = $request->getQueryString()) {
            $url .= '?'.$query;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                // Deliberately no Host/Origin/other headers: the Vite server
                // should not see the browser's tunnel host. This is a
                // server-to-server call; Vite doesn't gzip dev responses, so no
                // content-negotiation headers are needed either.
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            abort(502, "Vite dev server at $this->viteUrl is not reachable.");
        }

        $status = 200;
        $headers = [];

        foreach ($http_response_header as $header) {
            if (str_starts_with($header, 'HTTP/')) {
                $status = (int) explode(' ', $header)[1];

                continue;
            }

            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $headers += [trim($name) => trim($value)];
            }
        }

        return response($body, $status)->withHeaders($headers);
    }
}
