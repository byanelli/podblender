<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();

            // Resend's ID for the received email. Resend retries a webhook it considers undelivered, so the pair
            // is unique to make a repeat delivery a no-op.
            $table->string('resend_email_id');

            $table->string('sender');
            $table->string('subject', 1000)->nullable();
            $table->string('url', 2048)->nullable();
            $table->unsignedTinyInteger('status');
            $table->string('failure_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['feed_id', 'resend_email_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
    }
};
