<?php

namespace App\Models;

use App\Enums\InboundEmailStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InboundEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email received at a feed's inbound address, and the outcome of adding the link it contains.
 *
 * @property int $id
 * @property int $feed_id
 * @property string $resend_email_id
 * @property string $sender
 * @property ?string $subject
 * @property ?string $url
 * @property InboundEmailStatus $status
 * @property ?string $failure_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Feed $feed
 */
class InboundEmail extends Model
{
    /** @use HasFactory<InboundEmailFactory> */
    use HasFactory;

    protected $casts = [
        'status' => InboundEmailStatus::class,
    ];

    protected $hidden = [
        'resend_email_id',
    ];

    /**
     * @return BelongsTo<Feed, $this>
     */
    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function markAdded(string $url): void
    {
        $this->url = $url;
        $this->status = InboundEmailStatus::Added;
        $this->failure_reason = null;
        $this->save();
    }

    public function markFailed(string $reason, ?string $url = null): void
    {
        $this->url = $url;
        $this->status = InboundEmailStatus::Failed;
        $this->failure_reason = mb_substr($reason, 0, 1000);
        $this->save();
    }
}
