<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * feeds.id was created with integer('id')->primary(), which has no default on MySQL and Postgres, so inserting a feed
 * without an id fails there. It worked on SQLite because a single-column INTEGER primary key is an alias for the
 * rowid, which auto-increments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite can't ALTER a primary key in place, so rebuild the table and copy the rows with their ids.
            $this->rebuildForSqlite();

            return;
        }

        // MySQL and Postgres can modify the column in place.
        Schema::table('feeds', function (Blueprint $table) {
            $table->increments('id')->change();
        });
    }

    public function down(): void
    {
        // No-op: the previous key was a bug and isn't restored.
    }

    private function rebuildForSqlite(): void
    {
        Schema::create('feeds_rebuild', function (Blueprint $table) {
            // Every feeds column from the migrations before this one, with id auto-incrementing.
            $table->increments('id');
            $table->string('name');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('uuid')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->dateTime('subscribed_at')->nullable();
        });

        DB::table('feeds_rebuild')->insertUsing(
            ['id', 'name', 'created_at', 'updated_at', 'user_id', 'uuid', 'description', 'subscription_id', 'subscribed_at'],
            DB::table('feeds')->select(
                'id', 'name', 'created_at', 'updated_at', 'user_id', 'uuid', 'description', 'subscription_id', 'subscribed_at'
            )
        );

        Schema::drop('feeds');
        Schema::rename('feeds_rebuild', 'feeds');
    }
};
