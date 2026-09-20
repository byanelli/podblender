<?php

namespace App\Proxies\Contracts;

interface ProxyConfig
{
    /**
     * Whether the proxy's account details are set. A proxy is optional, so callers check this before calling
     * getUrlForDownload(), which throws without them.
     */
    public function isConfigured(): bool;

    /**
     * A proxy URL for one download. Call it once per download attempt.
     *
     * Every request in a download must leave from the same address. YouTube signs a media URL for the address that
     * requested it: the URL has an `ip` parameter, and `ip` is among the `sparams` the signature covers. If the
     * metadata and the media are fetched from different addresses, the download is refused, and the refusal looks the
     * same as a block. A residential pool changes its exit IP on every request by default.
     *
     * Each call returns a different address, because downloading everything from one address leads to a block.
     */
    public function getUrlForDownload(): string;

    /**
     * Whether the proxy terminates TLS with its own certificate, which we can't verify. A proxy that tunnels with
     * CONNECT keeps TLS end-to-end with YouTube and returns false.
     */
    public function requiresInsecureTls(): bool;
}
