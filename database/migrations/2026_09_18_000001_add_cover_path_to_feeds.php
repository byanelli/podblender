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
        Schema::table('feeds', function (Blueprint $table) {
            // Where the feed's show artwork is stored, on the same disk as the
            // clips. Null when there is none — a feed made before covers
            // existed, or one whose cover couldn't be drawn — and the RSS then
            // leaves the artwork tags out rather than point at nothing.
            $table->string('cover_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
