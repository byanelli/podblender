<?php

use App\Enums\AudioSourceType;
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
            $table->string('type')->default(AudioSourceType::Channel->value);

            // Who publishes the source. Always set, even for a channel, where
            // it repeats the channel's own name: a feed asking who published it
            // shouldn't have to know what type of source it came from. A
            // playlist is named for its contents ("Select Lectures"), so it
            // records the channel that owns it instead.
            $table->string('author_name')->default('');
        });

        // Existing sources are all channels, which author themselves.
        DB::table('audio_sources')->update([
            'author_name' => DB::raw('name'),
        ]);
    }

    public function down(): void
    {
        Schema::table('audio_sources', function (Blueprint $table) {
            $table->dropColumn(['type', 'author_name']);
        });
    }
};
