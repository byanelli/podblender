<?php

namespace App\Concerns;

use http\Url;
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

    protected function removeUtmCodesFromUrl(string $url): string {
        $url = Uri::fromBaseUri($url);

        if (empty($url->getQuery())) {
            return $url;
        } else {
            parse_str($url->getQuery(), $query);

            $query = collect($query)->filter(fn($val, $key) => !str_starts_with($key, 'utm_'))->all();

            return $url->withQuery(http_build_query($query))->toString();
        }
    }
}
