#!/usr/bin/env bash
set -euo pipefail

# The dev server runs on this port (see the "server" dev command in
# App\Providers\DevCommandsServiceProvider). When SERVER_PORT is set, serve and
# ngrok both use it; otherwise both fall back to 8000.
port="${SERVER_PORT:-8000}"

ngrok_host="${NGROK_HOST:-}"

# Concurrently starts everyone at once, so give the PHP server a moment to bind
# the socket before we try to open a tunnel; ngrok otherwise exits immediately
# on a connection-refused target.
for _ in $(seq 1 60); do
    if curl -sf -o /dev/null "http://127.0.0.1:${port}"; then
        break
    fi
    sleep 0.25
done

# Tunnel to the app. Use the account's assigned static domain (NGROK_HOST) so
# the public URL is stable; without --url ngrok picks a throwaway URL each run.
if [[ -n "${ngrok_host}" ]]; then
    ngrok http --url="${ngrok_host}" "${port}" >/dev/null 2>&1 &
else
    ngrok http "${port}" >/dev/null 2>&1 &
fi
ngrok_pid=$!

# Wait for the tunnel, then learn its public URL.
app_url=""
for _ in $(seq 1 40); do
    if [[ -n "${ngrok_host}" ]]; then
        app_url="https://${ngrok_host}"
    else
        # No fixed domain: read the live URL back from ngrok's local API.
        app_url="$(curl -sf "http://127.0.0.1:4040/api/tunnels" 2>/dev/null \
            | PORT="${port}" php -r '$t = json_decode(stream_get_contents(STDIN), true); foreach (($t["tunnels"] ?? []) as $tunnel) { if (str_contains($tunnel["config"]["addr"] ?? "", ":".getenv("PORT"))) { echo $tunnel["public_url"]; exit; } }')"
    fi

    if [[ -n "${app_url}" ]]; then
        break
    fi
    sleep 0.25
done

# Point the Vite "hot" file at this app's own public URL instead of the Vite
# dev server's loopback, so browsers load modules via the tunnel (Laravel's
# @vite directive resolves asset URLs from public/hot; app/Http/Controllers/
# ViteDevProxy forwards them back to Vite). This makes the whole UI work from
# any device at the single public URL. HMR's websocket is lost over the tunnel
# (it would target the same origin, which Laravel can't upgrade) — a hard
# refresh picks up changes.
if [[ -n "${app_url}" ]]; then
    printf '%s' "${app_url}" > "$(dirname "$0")/../public/hot"
    echo "[ngrok] public URL: ${app_url}"
    echo "[ngrok] Vite assets served same-origin via ${app_url}/resources/js/app.tsx"
fi

wait "${ngrok_pid}"
