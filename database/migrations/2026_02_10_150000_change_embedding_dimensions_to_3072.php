<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! (Schema::getConnection() instanceof PostgresConnection)) {
            return;
        }

        $tables = ['blogs', 'projects', 'shares'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->vector('embedding', dimensions: 3072)->nullable()->index()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! (Schema::getConnection() instanceof PostgresConnection)) {
            return;
        }

        $tables = ['blogs', 'projects', 'shares'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->vector('embedding', dimensions: 1536)->nullable()->index()->change();
            });
        }
    }
};
