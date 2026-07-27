<?php

use App\Models\Feed;
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
            $table->timestamp(Feed::COL_BACKFILL_SINCE)->nullable();

            // Whether to keep collecting episodes published from now on. Off
            // means the feed captures the source as it stands and is then left
            // alone; two subscribers to the same source can differ on this.
            $table->boolean(Feed::COL_TRACKS_NEW_EPISODES)->default(true);

            // When a subscription that isn't tracking new episodes finished its
            // one and only fill. Set means "done, don't sweep me again".
            $table->timestamp(Feed::COL_SUBSCRIPTION_FILLED_AT)->nullable();
        });

        // Preserve what subscribed_at actually meant, then let it mean what it
        // says: existing rows hold a backfill window in it.
        DB::table('feeds')
            ->whereNotNull(Feed::COL_SUBSCRIBED_AT)
            ->update([
                Feed::COL_BACKFILL_SINCE => DB::raw(Feed::COL_SUBSCRIBED_AT),
                Feed::COL_SUBSCRIBED_AT => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        // Put the backfill window back where it used to live, so the old code
        // still finds it.
        DB::table('feeds')
            ->whereNotNull(Feed::COL_BACKFILL_SINCE)
            ->update([Feed::COL_SUBSCRIBED_AT => DB::raw(Feed::COL_BACKFILL_SINCE)]);

        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn([
                Feed::COL_BACKFILL_SINCE,
                Feed::COL_TRACKS_NEW_EPISODES,
                Feed::COL_SUBSCRIPTION_FILLED_AT,
            ]);
        });
    }
};
