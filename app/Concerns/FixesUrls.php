<?php

namespace App\Concerns;

use Illuminate\Pipeline\Pipeline;
use League\Uri\Uri;

trait FixesUrls
{
    protected function ensureSchemeIsHttps(string $url): string
    {
        return Uri::fromBaseUri($url)->withScheme('https')->toString();
    }

    protected function removeWwwFromHost(string $url): string
    {
        $uri = Uri::fromBaseUri($url);

        $host = str_starts_with($uri->getHost(), 'www.')
            ? substr($uri->getHost(), strlen('www.'))
            : $uri->getHost();

        return $uri->withHost($host)->toString();
    }

    protected function fixUrlSchemeAndHost(string $url): string
    {
        return $this->removeWwwFromHost($this->ensureSchemeIsHttps($url));
    }

    protected function removeUtmCodesFromUrl(string $url): string
    {
        $url = Uri::fromBaseUri($url);

        if (empty($url->getQuery())) {
            return $url;
        } else {
            parse_str($url->getQuery(), $query);

            $query = collect($query)->filter(fn ($val, $key) => ! str_starts_with($key, 'utm_'))->all();

            $withoutUtm = $url->withQuery(http_build_query($query))->toString();

            // If every query param in the URL was a UTM code, it will end with a superfluous "?" which we remove before
            // returning.
            return str_ends_with($withoutUtm, '?')
                ? substr($withoutUtm, 0, strlen($withoutUtm) - 1)
                : $withoutUtm;
        }
    }

    protected function fixUrl(string $url): string
    {
        return (new Pipeline)->send($url)->through([
            $this->ensureSchemeIsHttps(...),
            $this->removeWwwFromHost(...),
            $this->removeUtmCodesFromUrl(...),
        ])->thenReturn();
    }
}
