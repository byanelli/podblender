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
            // How far back the subscriber asked us to reach. Until now this
            // shared subscribed_at, which was written as "a month ago" rather
            // than when they actually subscribed — so the column recorded the
            // backfill window and lied about its own name.
            $table->timestamp('backfill_since')->nullable();

            // Whether to keep collecting episodes published from now on. Off
            // means the feed captures the source as it stands and is then left
            // alone; two subscribers to the same source can differ on this.
            $table->boolean('tracks_new_episodes')->default(true);

            // When a subscription that isn't tracking new episodes finished its
            // one and only fill. Set means "done, don't sweep me again".
            $table->timestamp('subscription_filled_at')->nullable();
        });

        // Preserve what subscribed_at actually meant, then let it mean what it
        // says: existing rows hold a backfill window in it.
        DB::table('feeds')
            ->whereNotNull('subscribed_at')
            ->update([
                'backfill_since' => DB::raw('subscribed_at'),
                'subscribed_at'  => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        // Put the backfill window back where it used to live, so the old code
        // still finds it.
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
