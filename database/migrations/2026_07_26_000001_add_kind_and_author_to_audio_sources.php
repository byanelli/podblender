<?php

use App\Enums\AudioSourceKind;
use App\Models\AudioSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_sources', function (Blueprint $table) {
            // Every source that exists today is a channel; playlists are new.
            $table->string(AudioSource::COL_KIND)
                ->default(AudioSourceKind::Channel->value);

            // Who publishes the source, when that isn't its own name. A
            // playlist is named for its contents ("Select Lectures"), so it
            // records the channel that owns it to credit episodes to.
            $table->string(AudioSource::COL_AUTHOR_NAME)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audio_sources', function (Blueprint $table) {
            $table->dropColumn([AudioSource::COL_KIND, AudioSource::COL_AUTHOR_NAME]);
        });
    }
};
