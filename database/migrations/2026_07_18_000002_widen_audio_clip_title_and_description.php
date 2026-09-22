<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FindOrCreateAudioClip writes titles of up to 500 characters and descriptions of up to 1000, but both columns were
 * string(255). MySQL and Postgres throw or truncate on overflow; SQLite ignores the declared length, so tests passed.
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
