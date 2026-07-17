<?php

namespace App\Proxies\Contracts;

interface ProxyConfig
{
    /**
     * A proxy URL to route a single download through.
     *
     * The "single download" is a requirement rather than a description. YouTube signs the media URL it hands out
     * against the address that asked for it: the URL carries an `ip` parameter, and lists `ip` among the `sparams`
     * covered by its signature. Fetch the metadata from one address and the media from another and the signature no
     * longer matches, so the download is refused. A residential pool rotates its exit IP on every request unless told
     * otherwise, which is exactly that case, and it fails looking indistinguishable from being blocked.
     *
     * So an implementation that rotates has to pin one address for as long as a download takes, and hand back a
     * different one next time: downloading everything from a single address is what gets us blocked to begin with.
     * Calling this once per download attempt gives both.
     */
    public function getUrlForDownload(): string;

    /**
     * Whether the proxy terminates TLS with a certificate of its own, which leaves us unable to verify it. A proxy
     * that tunnels with CONNECT leaves the connection to YouTube end-to-end and doesn't need this; one that reads the
     * traffic in between does.
     */
    public function requiresInsecureTls(): bool;
}
