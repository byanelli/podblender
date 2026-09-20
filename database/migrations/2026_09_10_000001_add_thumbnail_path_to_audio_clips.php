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
            // Path to the clip's square artwork, on the same disk as its
            // audio. Null until the thumbnail job stores one, and permanently
            // null when the platform has no artwork for the clip.
            $table->string('thumbnail_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
