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
            // Where the clip's square episode artwork is stored, on the same
            // disk as its audio. Null until the thumbnail job has stored one —
            // and for good, for a clip whose platform offered no artwork.
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
