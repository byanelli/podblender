<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audio_clips.title and audio_clips.description were both string(255), but FindOrCreateAudioClip truncates titles at
 * 497 characters and descriptions at 997 before saving them. On MySQL/Postgres that overflows the column and either
 * throws or silently truncates; SQLite ignores the declared length, which is why it hasn't bitten us in tests. Widen
 * the columns to match what the code actually writes: a title comfortably over 500, and a description with no practical
 * ceiling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->string('title', 500)->change();
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->string('description', 255)->change();
        });
    }
};
