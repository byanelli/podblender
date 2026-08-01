<?php

use App\Enums\AudioSourceType;
use App\Models\AudioSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_sources', function (Blueprint $table) {
            // Every source that exists today is a channel; playlists are new.
            $table->string(AudioSource::COL_TYPE)
                ->default(AudioSourceType::Channel->value);

            // Who publishes the source. Always set, even for a channel, where
            // it repeats the channel's own name: a feed asking who published it
            // shouldn't have to know what type of source it came from. A
            // playlist is named for its contents ("Select Lectures"), so it
            // records the channel that owns it instead.
            $table->string(AudioSource::COL_AUTHOR_NAME)->default('');
        });

        // Existing sources are all channels, which author themselves.
        DB::table('audio_sources')->update([
            AudioSource::COL_AUTHOR_NAME => DB::raw(AudioSource::COL_NAME),
        ]);
    }

    public function down(): void
    {
        Schema::table('audio_sources', function (Blueprint $table) {
            $table->dropColumn([AudioSource::COL_TYPE, AudioSource::COL_AUTHOR_NAME]);
        });
    }
};
