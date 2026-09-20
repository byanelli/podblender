<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            // The earliest publication date to backfill from. This was stored
            // in subscribed_at, which was set to a month before subscribing.
            $table->timestamp('backfill_since')->nullable();

            // Whether to add episodes published after subscribing. Set per
            // feed, so two subscribers to the same source can differ.
            $table->boolean('tracks_new_episodes')->default(true);

            // When a feed that doesn't track new episodes finished its single
            // fill. Once set, the feed is not updated again.
            $table->timestamp('subscription_filled_at')->nullable();
        });

        // Existing rows have the backfill date in subscribed_at. Move it, and
        // set subscribed_at to the feed's creation date.
        DB::table('feeds')
            ->whereNotNull('subscribed_at')
            ->update([
                'backfill_since' => DB::raw('subscribed_at'),
                'subscribed_at'  => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        // Move the backfill date back into subscribed_at.
        DB::table('feeds')
            ->whereNotNull('backfill_since')
            ->update(['subscribed_at' => DB::raw('backfill_since')]);

        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn([
                'backfill_since',
                'tracks_new_episodes',
                'subscription_filled_at',
            ]);
        });
    }
};
