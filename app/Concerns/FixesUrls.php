<?php

namespace App\Concerns;

use League\Uri\Uri;

trait FixesUrls
{
    protected function ensureSchemeIsHttps(string $url): string {
        return Uri::fromBaseUri($url)->withScheme('https')->toString();
    }

    protected function removeWwwFromHost(string $url): string {
        $uri = Uri::fromBaseUri($url);

        $host = str_starts_with($uri->getHost(), 'www.')
            ? substr($uri->getHost(), strlen('www.'))
            : $uri->getHost();

        return $uri->withHost($host)->toString();
    }

    protected function fixUrlSchemeAndHost(string $url): string {
        return $this->removeWwwFromHost($this->ensureSchemeIsHttps($url));
    }
}
