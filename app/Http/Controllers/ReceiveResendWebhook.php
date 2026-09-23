<?php

namespace App\Http\Controllers;

use App\Enums\InboundEmailStatus;
use App\Events\InboundEmailUpdated;
use App\Http\Requests\Resend\WebhookRequest;
use App\InboundEmails\RecipientFeedFinder;
use App\Jobs\ProcessInboundEmail;
use App\Models\InboundEmail;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Resend\Exceptions\WebhookSignatureVerificationException;
use Resend\WebhookSignature;

/**
 * Receives Resend's webhook for email sent to a feed's inbound address, and queues the email for processing.
 *
 * Mail that can't be matched to a feed gets a 200 like any other, so a sender can't test which addresses exist, and
 * so Resend doesn't retry it.
 */
readonly class ReceiveResendWebhook
{
    /** Emails a feed accepts per hour, in case its address leaks. */
    private const int EMAILS_PER_HOUR = 30;

    public function __invoke(
        WebhookRequest $request,
        Config $config,
        RecipientFeedFinder $feedFinder,
        RateLimiter $rateLimiter,
        Dispatcher $dispatcher,
        Events $events,
        LoggerInterface $logger,
    ): Response {
        $secret = (string) $config->get('services.resend.webhook_secret');

        // An empty secret would make any signature computed with an empty key valid.
        abort_if($secret === '', 404);

        try {
            WebhookSignature::verify($request->payload, $request->signatureHeaders(), $secret);
        } catch (WebhookSignatureVerificationException) {
            abort(401);
        }

        if ($request->type !== 'email.received') {
            return response()->noContent();
        }

        $data = $request->data;

        $feed = $feedFinder->find([...$data->receivedFor, ...$data->to, ...$data->cc]);

        if ($feed === null) {
            $logger->info("Inbound email {$data->emailId} matched no feed");

            return response()->noContent();
        }

        $rateLimitKey = "inbound-email:{$feed->id}";

        if ($rateLimiter->tooManyAttempts($rateLimitKey, self::EMAILS_PER_HOUR)) {
            $logger->warning("Dropped inbound email {$data->emailId}: feed {$feed->id} is over its hourly limit");

            return response()->noContent();
        }

        $inboundEmail = InboundEmail::query()->firstOrCreate(
            ['feed_id' => $feed->id, 'resend_email_id' => $data->emailId],
            [
                'sender'  => mb_substr($data->from ?? '', 0, 255),
                'subject' => $data->subject === null ? null : mb_substr($data->subject, 0, 1000),
                'status'  => InboundEmailStatus::Pending,
            ],
        );

        if ($inboundEmail->wasRecentlyCreated) {
            $rateLimiter->hit($rateLimitKey, 3600);

            $dispatcher->dispatch(new ProcessInboundEmail($inboundEmail));

            $events->dispatch(new InboundEmailUpdated($feed));
        }

        return response()->noContent();
    }
}
