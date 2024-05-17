<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audio_clips', function (Blueprint $table) {
            $table->increments('id');
            $table->string('platform_id')->unique('youtube_videos_platform_id_unique');
            $table->string('guid')->unique('youtube_videos_guid_unique');
            $table->integer('audio_source_id');
            $table->string('title');
            $table->string('description');
            $table->integer('duration');
            $table->integer('size');
            $table->string('storage_path')->unique('youtube_videos_storage_path_unique');
            $table->boolean('processing');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audio_clips');
    }
};
