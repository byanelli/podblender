<?php

namespace Tests\Jobs;

use App\Apis\Resend\ReceivedEmail;
use App\Enums\InboundEmailStatus;
use App\Enums\PlatformType;
use App\Events\InboundEmailUpdated;
use App\Jobs\ProcessInboundEmail;
use App\Models\Feed;
use App\Models\InboundEmail;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\PlatformOperation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPlatform;
use Tests\Concerns\FakesResend;
use Tests\TestCase;

class ProcessInboundEmailTest extends TestCase
{
    use FakesPlatform, FakesResend;

    private const string URL = 'https://www.youtube.com/watch?v=abc123';

    protected function setUp(): void
    {
        parent::setUp();

        // Adding a clip queues its download.
        Bus::fake();
        Event::fake([InboundEmailUpdated::class]);
    }

    #[Test]
    public function it_adds_the_linked_clip_to_the_feed()
    {
        $this->fakeResend(new ReceivedEmail(subject: 'Watch this', text: 'Here: '.self::URL, html: null));
        $this->fakePlatform(clipMetadata: $this->clipMetadata());

        $inboundEmail = $this->inboundEmail();

        $this->process($inboundEmail);

        $inboundEmail->refresh();
        $this->assertEquals(InboundEmailStatus::Added, $inboundEmail->status);
        $this->assertEquals(self::URL, $inboundEmail->url);
        $this->assertEquals(self::URL, $inboundEmail->feed->audioClips()->sole()->platform_url);
        Event::assertDispatched(InboundEmailUpdated::class);
    }

    #[Test]
    public function it_records_an_email_with_no_link()
    {
        $this->fakeResend(new ReceivedEmail(subject: 'Hi', text: 'Nothing to see.', html: null));

        $inboundEmail = $this->inboundEmail();

        $this->process($inboundEmail);

        $inboundEmail->refresh();
        $this->assertEquals(InboundEmailStatus::Failed, $inboundEmail->status);
        $this->assertEquals('No link found in the email.', $inboundEmail->failure_reason);
        $this->assertTrue($inboundEmail->feed->audioClips()->doesntExist());
    }

    #[Test]
    public function it_records_a_link_the_feed_page_would_also_reject()
    {
        $url = 'https://example.com/'.str_repeat('a', 300);
        $this->fakeResend(new ReceivedEmail(subject: null, text: $url, html: null));

        $inboundEmail = $this->inboundEmail();

        $this->process($inboundEmail);

        $inboundEmail->refresh();
        $this->assertEquals(InboundEmailStatus::Failed, $inboundEmail->status);
        $this->assertEquals($url, $inboundEmail->url);
        $this->assertNotNull($inboundEmail->failure_reason);
    }

    #[Test]
    public function it_records_a_platform_error()
    {
        $this->fakeResend(new ReceivedEmail(subject: self::URL, text: null, html: null));
        $this->fakePlatform(clipMetadataError: new PlatformException(
            PlatformType::YouTube,
            PlatformOperation::Metadata,
            new \RuntimeException('yt-dlp exited with code 1'),
        ));

        $inboundEmail = $this->inboundEmail();

        $this->process($inboundEmail);

        $inboundEmail->refresh();
        $this->assertEquals(InboundEmailStatus::Failed, $inboundEmail->status);
        $this->assertEquals('Error getting metadata from YouTube', $inboundEmail->failure_reason);
    }

    #[Test]
    public function it_lets_a_failure_to_fetch_the_email_propagate_so_the_job_is_retried()
    {
        $this->fakeResend(error: new \RuntimeException('Resend is down'));

        $this->expectExceptionMessage('Resend is down');

        $this->process($this->inboundEmail());
    }

    #[Test]
    public function it_marks_the_email_failed_when_the_retries_run_out()
    {
        $inboundEmail = $this->inboundEmail();

        (new ProcessInboundEmail($inboundEmail))->failed(new \RuntimeException('Resend is down'));

        $inboundEmail->refresh();
        $this->assertEquals(InboundEmailStatus::Failed, $inboundEmail->status);
        $this->assertEquals('The email could not be processed.', $inboundEmail->failure_reason);
        Event::assertDispatched(InboundEmailUpdated::class);
    }

    private function inboundEmail(): InboundEmail
    {
        return InboundEmail::factory()->create(['feed_id' => Feed::factory()->create()->id]);
    }

    private function process(InboundEmail $inboundEmail): void
    {
        $this->app->call([new ProcessInboundEmail($inboundEmail), 'handle']);
    }

    private function clipMetadata(): ClipMetadata
    {
        return new ClipMetadata(
            title: 'A talk',
            description: 'About things.',
            canonicalUrl: self::URL,
            publishedAt: now()->subYear(),
            source: new SourceMetadata(
                name: 'A channel',
                canonicalUrl: 'https://www.youtube.com/channel/xyz',
                authorName: 'A channel',
            ),
        );
    }
}
