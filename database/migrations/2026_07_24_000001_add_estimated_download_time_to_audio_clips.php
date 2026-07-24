<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            // The platform's conservative guess at one download's wall-clock
            // time, used by DownloadAndStoreAudioClip to size its timeout.
            // Null for clips created before the platform reported an estimate.
            $table->unsignedInteger('estimated_download_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropColumn('estimated_download_time');
        });
    }
};
