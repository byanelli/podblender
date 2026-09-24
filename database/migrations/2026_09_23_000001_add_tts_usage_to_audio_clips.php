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
            // What narrating an article cost. Null for clips that weren't narrated.
            $table->string('tts_model')->nullable();
            $table->unsignedInteger('tts_input_tokens')->nullable();
            $table->unsignedInteger('tts_output_tokens')->nullable();
            // USD, at the price in effect when the clip was narrated.
            $table->decimal('tts_cost', 10, 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropColumn(['tts_model', 'tts_input_tokens', 'tts_output_tokens', 'tts_cost']);
        });
    }
};
