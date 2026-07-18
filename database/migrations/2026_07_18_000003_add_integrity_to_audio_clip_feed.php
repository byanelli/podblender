<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * audio_clip_feed was created as two bare integer columns with no constraints: nothing stopped the same clip being
 * attached to the same feed twice (a duplicate episode in the RSS), and deleting a clip or feed left orphaned pivot
 * rows behind. This adds the unique pairing and the cascade-deleting foreign keys the table should have had.
 *
 * The table is rebuilt rather than altered in place. SQLite can't add a foreign key to an existing table at all, and
 * deduping an unkeyed pivot on MySQL is awkward; copying the rows into a fresh table through a GROUP BY does both the
 * dedupe and the constraint-adding in one portable step, on every driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_clip_feed_rebuild', function (Blueprint $table) {
            $table->integer('audio_clip_id');
            $table->integer('feed_id');
            $table->timestamp('published_at')->nullable();

            $table->unique(['audio_clip_id', 'feed_id'], 'audio_clip_feed_audio_clip_id_feed_id_unique');
            $table->foreign('audio_clip_id')->references('id')->on('audio_clips')->cascadeOnDelete();
            $table->foreign('feed_id')->references('id')->on('feeds')->cascadeOnDelete();
        });

        // Copy the rows across, collapsing any duplicate (clip, feed) pairing to a single row. Existing prod data may
        // hold duplicates from before the unique index existed; MIN(published_at) keeps the earliest date the pairing
        // was ever presented at, so a surviving row is never newer than what was there before.
        DB::table('audio_clip_feed_rebuild')->insertUsing(
            ['audio_clip_id', 'feed_id', 'published_at'],
            DB::table('audio_clip_feed')
                ->select('audio_clip_id', 'feed_id')
                ->selectRaw('MIN(published_at) as published_at')
                ->groupBy('audio_clip_id', 'feed_id')
        );

        Schema::drop('audio_clip_feed');
        Schema::rename('audio_clip_feed_rebuild', 'audio_clip_feed');
    }

    public function down(): void
    {
        Schema::create('audio_clip_feed_rebuild', function (Blueprint $table) {
            $table->integer('audio_clip_id');
            $table->integer('feed_id');
            $table->timestamp('published_at')->nullable();
        });

        DB::table('audio_clip_feed_rebuild')->insertUsing(
            ['audio_clip_id', 'feed_id', 'published_at'],
            DB::table('audio_clip_feed')->select('audio_clip_id', 'feed_id', 'published_at')
        );

        Schema::drop('audio_clip_feed');
        Schema::rename('audio_clip_feed_rebuild', 'audio_clip_feed');
    }
};
