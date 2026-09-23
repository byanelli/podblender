<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            // The local part of the feed's inbound email address. Null for subscription feeds.
            $table->string('inbound_email_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropUnique(['inbound_email_token']);
            $table->dropColumn('inbound_email_token');
        });
    }
};
