<?php

namespace Tests\Http\Controllers;

use App\Enums\InboundEmailStatus;
use App\Events\InboundEmailUpdated;
use App\Jobs\ProcessInboundEmail;
use App\Models\Feed;
use App\Models\InboundEmail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceiveResendWebhookTest extends TestCase
{
    private const string SECRET = 'whsec_dGVzdC1zaWduaW5nLXNlY3JldA==';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.resend.webhook_secret' => self::SECRET,
            'services.resend.inbound_domain' => 'mail.example.com',
        ]);

        Bus::fake();
        Event::fake([InboundEmailUpdated::class]);
    }

    #[Test]
    public function it_records_the_email_and_queues_it_for_processing()
    {
        $feed = Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $this->postWebhook($this->receivedEvent(to: ['abc123@mail.example.com']))->assertNoContent();

        $inboundEmail = InboundEmail::query()->sole();
        $this->assertEquals($feed->id, $inboundEmail->feed_id);
        $this->assertEquals('email-1', $inboundEmail->resend_email_id);
        $this->assertEquals('me@example.org', $inboundEmail->sender);
        $this->assertEquals('A talk', $inboundEmail->subject);
        $this->assertEquals(InboundEmailStatus::Pending, $inboundEmail->status);

        Bus::assertDispatched(ProcessInboundEmail::class, fn ($job) => $job->inboundEmail->is($inboundEmail));
        Event::assertDispatched(InboundEmailUpdated::class);
    }

    #[Test]
    public function it_matches_the_feed_on_the_address_the_mail_was_delivered_to()
    {
        // A forwarded email keeps its original To header.
        $feed = Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $this->postWebhook($this->receivedEvent(
            to: ['me@gmail.com'],
            receivedFor: ['abc123@mail.example.com'],
        ));

        $this->assertEquals($feed->id, InboundEmail::query()->sole()->feed_id);
    }

    #[Test]
    public function it_ignores_a_repeat_delivery_of_the_same_email()
    {
        Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $this->postWebhook($this->receivedEvent(to: ['abc123@mail.example.com']));
        $this->postWebhook($this->receivedEvent(to: ['abc123@mail.example.com']))->assertNoContent();

        $this->assertDatabaseCount('inbound_emails', 1);
        Bus::assertDispatchedTimes(ProcessInboundEmail::class, 1);
    }

    #[Test]
    public function it_accepts_and_drops_mail_for_an_unknown_address()
    {
        $this->postWebhook($this->receivedEvent(to: ['nobody@mail.example.com']))->assertNoContent();

        $this->assertDatabaseCount('inbound_emails', 0);
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function it_ignores_other_event_types()
    {
        Feed::factory()->create(['inbound_email_token' => 'abc123']);

        $event = $this->receivedEvent(to: ['abc123@mail.example.com']);
        $event['type'] = 'email.delivered';

        $this->postWebhook($event)->assertNoContent();

        $this->assertDatabaseCount('inbound_emails', 0);
    }

    #[Test]
    public function it_drops_mail_beyond_the_hourly_limit_for_a_feed()
    {
        Feed::factory()->create(['inbound_email_token' => 'abc123']);

        foreach (range(1, 31) as $i) {
            $this->postWebhook($this->receivedEvent(to: ['abc123@mail.example.com'], emailId: "email-$i"))
                ->assertNoContent();
        }

        $this->assertDatabaseCount('inbound_emails', 30);
    }

    #[Test]
    public function it_rejects_a_bad_signature()
    {
        $body = json_encode($this->receivedEvent(to: ['abc123@mail.example.com']));

        $this->withExceptionHandling()->call('POST', '/webhooks/resend', [], [], [], [
            'CONTENT_TYPE'        => 'application/json',
            'HTTP_SVIX_ID'        => 'msg_1',
            'HTTP_SVIX_TIMESTAMP' => (string) time(),
            'HTTP_SVIX_SIGNATURE' => 'v1,'.base64_encode('wrong'),
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('inbound_emails', 0);
    }

    #[Test]
    public function it_rejects_a_request_without_signature_headers()
    {
        $this->withExceptionHandling()
            ->postJson('/webhooks/resend', $this->receivedEvent(to: ['abc123@mail.example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['header.svix-id']);

        $this->assertDatabaseCount('inbound_emails', 0);
    }

    #[Test]
    public function it_is_disabled_when_no_signing_secret_is_configured()
    {
        config(['services.resend.webhook_secret' => null]);

        $this->withExceptionHandling();

        $this->postWebhook($this->receivedEvent(to: ['abc123@mail.example.com']))->assertNotFound();
    }

    /**
     * @param  list<string>  $to
     * @param  list<string>  $receivedFor
     * @return array<string, mixed>
     */
    private function receivedEvent(array $to, array $receivedFor = [], string $emailId = 'email-1'): array
    {
        return [
            'type'       => 'email.received',
            'created_at' => '2026-09-22T12:00:00.000Z',
            'data'       => [
                'email_id'     => $emailId,
                'from'         => 'me@example.org',
                'to'           => $to,
                'cc'           => [],
                'bcc'          => [],
                'received_for' => $receivedFor,
                'subject'      => 'A talk',
            ],
        ];
    }

    /**
     * Posts the event signed the way Resend signs it (Svix's scheme).
     *
     * @param  array<string, mixed>  $event
     */
    private function postWebhook(array $event): TestResponse
    {
        $body = (string) json_encode($event);
        $id = 'msg_'.md5($body);
        $timestamp = time();
        $key = base64_decode(substr(self::SECRET, strlen('whsec_')));
        $signature = base64_encode(hash_hmac('sha256', "$id.$timestamp.$body", $key, true));

        return $this->call('POST', '/webhooks/resend', [], [], [], [
            'CONTENT_TYPE'        => 'application/json',
            'HTTP_SVIX_ID'        => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => "v1,$signature",
        ], $body);
    }
}
