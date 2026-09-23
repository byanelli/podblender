<?php

namespace App\Jobs;

use App\Actions\AddClipToFeed;
use App\Apis\Resend\Contracts\Client as Resend;
use App\Events\InboundEmailUpdated;
use App\InboundEmails\LinkExtractor;
use App\Jobs\Concerns\InjectsFailureDependencies;
use App\Models\InboundEmail;
use App\Platforms\Exceptions\PlatformException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

/**
 * Fetches a received email from Resend and adds the first link in it to the email's feed.
 *
 * Only a failure to fetch the email is retried. A missing link or a platform error is recorded on the InboundEmail.
 */
class ProcessInboundEmail implements ShouldQueue
{
    use Dispatchable, InjectsFailureDependencies, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly InboundEmail $inboundEmail) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(
        Resend $resend,
        LinkExtractor $linkExtractor,
        Validation $validation,
        AddClipToFeed $addClipToFeed,
        Events $events,
    ): void {
        $inboundEmail = $this->inboundEmail->load('feed');

        $url = $linkExtractor->firstLink($resend->receivedEmail($inboundEmail->resend_email_id));

        if ($url === null) {
            $inboundEmail->markFailed('No link found in the email.');
        } else {
            // The same rules as the feed page's form.
            $validator = $validation->make(['url' => $url], ['url' => ['url:http,https', 'max:255']]);

            if ($validator->fails()) {
                $inboundEmail->markFailed($validator->errors()->first('url'), mb_substr($url, 0, 2048));
            } else {
                try {
                    $addClipToFeed($inboundEmail->feed, $url);
                    $inboundEmail->markAdded($url);
                } catch (PlatformException $e) {
                    $inboundEmail->markFailed($e->getMessage(), $url);
                }
            }
        }

        $events->dispatch(new InboundEmailUpdated($inboundEmail->feed));
    }

    public function handleFailure(?\Throwable $e, LoggerInterface $logger, Events $events): void
    {
        $logger->error(
            "Gave up processing inbound email {$this->inboundEmail->id}: ".($e?->getMessage() ?? 'no reason given')
        );

        $inboundEmail = $this->inboundEmail->load('feed');
        $inboundEmail->markFailed('The email could not be processed.');

        $events->dispatch(new InboundEmailUpdated($inboundEmail->feed));
    }
}
