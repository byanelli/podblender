<?php

namespace Database\Concerns;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Migration
 */
trait DetectsConnectionType
{
    protected function isSqlite(): bool
    {
        $conn = DB::connection($this->getConnection());

        return $conn instanceof SQLiteConnection;
    }

    protected function nullableIfSqlite(ColumnDefinition $column): ColumnDefinition
    {
        return $this->isSqlite()
            ? $column->nullable()
            : $column;
    }
}
