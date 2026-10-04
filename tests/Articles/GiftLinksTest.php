<?php

namespace Tests\Articles;

use App\Articles\GiftLinks;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GiftLinksTest extends TestCase
{
    private function giftLinks(): GiftLinks
    {
        return new GiftLinks([
            'theatlantic.com' => ['gift'],
            'ft.com'          => ['accessToken'],
        ]);
    }

    #[Test]
    public function it_recognizes_a_gift_link_with_or_without_www()
    {
        $this->assertTrue($this->giftLinks()->isGiftLink('https://www.theatlantic.com/a/1/?gift=abc'));
        $this->assertTrue($this->giftLinks()->isGiftLink('https://theatlantic.com/a/1/?gift=abc'));
    }

    #[Test]
    public function it_does_not_recognize_another_hosts_parameter()
    {
        $this->assertFalse($this->giftLinks()->isGiftLink('https://www.theatlantic.com/a/1/?accessToken=abc'));
        $this->assertFalse($this->giftLinks()->isGiftLink('https://example.com/a/1/?gift=abc'));
        $this->assertFalse($this->giftLinks()->isGiftLink('https://www.theatlantic.com/a/1/'));
    }

    #[Test]
    public function it_removes_gift_parameters_and_keeps_the_rest()
    {
        $this->assertSame(
            'https://www.theatlantic.com/a/1/',
            $this->giftLinks()->removeGiftParams('https://www.theatlantic.com/a/1/?gift=abc'),
        );

        $this->assertSame(
            'https://www.ft.com/content/x?page=2',
            $this->giftLinks()->removeGiftParams('https://www.ft.com/content/x?accessToken=abc&page=2'),
        );
    }

    #[Test]
    public function it_leaves_a_url_without_gift_parameters_unchanged()
    {
        $url = 'https://example.com/a/1/?gift=abc&b=c%20d';

        $this->assertSame($url, $this->giftLinks()->removeGiftParams($url));
    }
}
