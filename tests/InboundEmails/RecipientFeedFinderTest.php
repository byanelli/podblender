<?php

namespace Tests\InboundEmails;

use App\InboundEmails\RecipientFeedFinder;
use App\Models\Feed;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecipientFeedFinderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.resend.inbound_domain' => 'mail.example.com']);
    }

    #[Test]
    public function it_finds_the_feed_by_the_local_part_of_its_address()
    {
        $feed = Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $this->assertTrue($feed->is($this->finder()->find(['abc123@mail.example.com'])));
    }

    #[Test]
    public function it_ignores_case_and_display_names()
    {
        $feed = Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $this->assertTrue($feed->is($this->finder()->find(['"My Feed" <ABC123@Mail.Example.com>'])));
    }

    #[Test]
    public function it_skips_addresses_at_other_domains()
    {
        $feed = Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $found = $this->finder()->find(['me@gmail.com', 'abc123@mail.example.com']);

        $this->assertTrue($feed->is($found));
    }

    #[Test]
    public function it_uses_only_the_first_address_at_the_inbound_domain()
    {
        Feed::factory()->create(['inbound_email_token' => 'second']);

        $this->assertNull($this->finder()->find(['unknown@mail.example.com', 'second@mail.example.com']));
    }

    #[Test]
    public function it_finds_nothing_when_the_inbound_domain_is_not_configured()
    {
        config(['services.resend.inbound_domain' => null]);
        Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $this->assertNull($this->finder()->find(['abc123@mail.example.com']));
    }

    private function finder(): RecipientFeedFinder
    {
        return $this->app->make(RecipientFeedFinder::class);
    }
}
