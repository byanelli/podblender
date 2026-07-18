<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The feeds table was created with integer('id')->primary() rather than an auto-incrementing key. On MySQL and
 * Postgres that produces a plain primary key with no default, so inserting a feed without supplying an id fails. It
 * only ever worked because SQLite makes any single-column INTEGER primary key an alias for the rowid, which does
 * auto-increment — an accident of the test driver, not a property of the schema. This makes the key a real
 * auto-increment primary key everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite can't ALTER a primary key in place, so rebuild the table with an AUTOINCREMENT key and copy the
            // rows across, preserving their existing ids. (Functionally SQLite already auto-increments this column via
            // the rowid alias; the rebuild is what makes the declared schema say so.)
            $this->rebuildForSqlite();

            return;
        }

        // MySQL and Postgres support modifying the column in place. increments() gives us the unsigned
        // auto-incrementing primary key the table should have had from the start.
        Schema::table('feeds', function (Blueprint $table) {
            $table->increments('id')->change();
        });
    }

    public function down(): void
    {
        // The previous shape (a plain, non-auto-incrementing integer primary key) was a bug; there's nothing worth
        // restoring it to, so this is a no-op.
    }

    private function rebuildForSqlite(): void
    {
        Schema::create('feeds_rebuild', function (Blueprint $table) {
            // The full current shape of feeds, assembled from every migration that has touched it, with id now a
            // proper auto-incrementing primary key.
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
