<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The date a clip is presented as published belongs to the pairing of a clip and a feed rather than to the clip: the
 * same clip can sit in a subscription, where it should keep the date the platform published it, and in a hand-made
 * feed, where the day it was added is what makes it turn up as a new episode instead of years down the listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_clip_feed', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
        });

        // Give the rows that already exist a date, so that no existing feed loses its ordering. Feeds with a
        // subscription take the clip's publication date. The rest are hand-made, and want the date the clip was added
        // to them, which was never recorded; the date the clip was created is the closest thing available, and for a
        // hand-made feed a clip is usually created by being added to it.
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
