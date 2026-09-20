<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A clip's published date is recorded per feed. In a subscription feed it is the platform's publication date; in a
 * hand-made feed it is the day the clip was added, so the clip is listed as a new episode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_clip_feed', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
        });

        // Backfill existing rows so feeds keep their ordering. Subscription feeds take the clip's publication date.
        // Hand-made feeds take the clip's created_at, because the date a clip was added to a feed was never recorded.
        $subscriptionFeedIds = DB::table('feeds')->whereNotNull('subscription_id')->pluck('id')->all();

        foreach (DB::table('audio_clips')->get(['id', 'published_at', 'created_at']) as $clip) {
            $rows = fn () => DB::table('audio_clip_feed')->where('audio_clip_id', $clip->id);

            $rows()
                ->whereIn('feed_id', $subscriptionFeedIds)
                ->update(['published_at' => $clip->published_at]);

            $rows()
                ->whereNotIn('feed_id', $subscriptionFeedIds)
                ->update(['published_at' => $clip->created_at]);
        }
    }

    public function down(): void
    {
        Schema::table('audio_clip_feed', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
