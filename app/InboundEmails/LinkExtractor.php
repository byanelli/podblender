<?php

namespace App\InboundEmails;

use App\Apis\Resend\ReceivedEmail;

/**
 * Finds the link to add in a received email: the first URL in the subject, then in the plain-text body, then in the
 * HTML body's text, then in the HTML body's links.
 */
class LinkExtractor
{
    private const string URL_PATTERN = '~https?://[^\s<>"\'`]+~i';

    public function firstLink(ReceivedEmail $email): ?string
    {
        $html = $email->html ?? '';

        return $this->firstUrlIn($email->subject ?? '')
            ?? $this->firstUrlIn($email->text ?? '')
            ?? $this->firstUrlIn($this->htmlToText($html))
            ?? $this->firstHref($html);
    }

    private function firstUrlIn(string $text): ?string
    {
        if (preg_match(self::URL_PATTERN, $text, $matches) !== 1) {
            return null;
        }

        return $this->trimTrailingPunctuation($matches[0]);
    }

    private function firstHref(string $html): ?string
    {
        if (preg_match('~<a\s[^>]*href\s*=\s*["\'](https?://[^"\']+)["\']~i', $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    private function htmlToText(string $html): string
    {
        // strip_tags keeps the contents of these elements, which can contain URLs that aren't part of the message.
        $html = (string) preg_replace('~<(head|style|script)\b.*?</\1>~is', ' ', $html);

        // Adjacent block elements would otherwise run a URL into the next word.
        $html = str_replace('<', ' <', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Removes sentence punctuation that follows a URL in prose. A closing bracket is kept when the URL contains its
     * opening bracket, as in Wikipedia URLs.
     */
    private function trimTrailingPunctuation(string $url): string
    {
        while ($url !== '') {
            $last = substr($url, -1);

            $isUnbalancedBracket = match ($last) {
                ')'     => substr_count($url, '(') < substr_count($url, ')'),
                ']'     => substr_count($url, '[') < substr_count($url, ']'),
                default => false,
            };

            if (! $isUnbalancedBracket && ! in_array($last, ['.', ',', ';', ':', '!', '?'], true)) {
                break;
            }

            $url = substr($url, 0, -1);
        }

        return $url;
    }
}
