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
            // Every existing source is a channel.
            $table->string('type')->default(AudioSourceType::Channel->value);

            // Who publishes the source. Set for every type so callers don't
            // branch on it: a channel repeats its name, and a playlist records
            // the name of its channel.
            $table->string('author_name')->default('');
        });

        // Existing sources are all channels, so the author is the source's name.
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
