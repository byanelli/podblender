<?php

namespace App\InboundEmails;

use App\Models\Feed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;

/**
 * Finds the feed that a received email was addressed to.
 */
readonly class RecipientFeedFinder
{
    public function __construct(private Config $config) {}

    /**
     * Addresses are tried in the order given. Resend's received_for comes first because it is the address the mail
     * was delivered to, and differs from To when the email was forwarded or the feed address was in Bcc.
     *
     * @param  list<string>  $addresses
     */
    public function find(array $addresses): ?Feed
    {
        $domain = Str::lower((string) $this->config->get('services.resend.inbound_domain'));

        if ($domain === '') {
            return null;
        }

        // TODO: support an email addressed to several feeds. Only the first feed address is used and the rest are
        // ignored.
        $token = collect($addresses)
            ->map(fn (string $address) => $this->bareAddress($address))
            ->filter(fn (?string $address) => $address !== null && Str::after($address, '@') === $domain)
            ->map(fn (string $address) => Str::before($address, '@'))
            ->first();

        if ($token === null) {
            return null;
        }

        return Feed::query()->where('inbound_email_token', $token)->first();
    }

    /**
     * The lowercased address in "Name <address>" or a bare address.
     */
    private function bareAddress(string $address): ?string
    {
        if (preg_match('~<([^<>\s]+@[^<>\s]+)>~', $address, $matches) === 1) {
            return Str::lower($matches[1]);
        }

        $address = trim($address);

        return str_contains($address, '@') ? Str::lower($address) : null;
    }
}
