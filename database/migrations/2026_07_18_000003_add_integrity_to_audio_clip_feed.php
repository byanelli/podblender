<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * audio_clip_feed had no constraints, so a clip could be attached to a feed twice (a duplicate episode in the RSS), and
 * deleting a clip or feed left its pivot rows. This adds a unique index on the pair and cascading foreign keys.
 *
 * The table is rebuilt because SQLite can't add a foreign key to an existing table. Copying through a GROUP BY also
 * removes duplicates on every driver.
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

        // Copy one row per (clip, feed) pair, with the earliest published_at among its duplicates. Rows whose clip or
        // feed has been deleted are skipped, since the new foreign keys would reject them.
        DB::table('audio_clip_feed_rebuild')->insertUsing(
            ['audio_clip_id', 'feed_id', 'published_at'],
            DB::table('audio_clip_feed')
                ->whereExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('audio_clips')
                    ->whereColumn('audio_clips.id', 'audio_clip_feed.audio_clip_id'))
                ->whereExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('feeds')
                    ->whereColumn('feeds.id', 'audio_clip_feed.feed_id'))
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
