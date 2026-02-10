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
        $isPostgres = Schema::getConnection() instanceof PostgresConnection;
        $tables = ['blogs', 'projects', 'shares'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($isPostgres) {
                if ($isPostgres) {
                    $blueprint->vector('embedding', dimensions: 1536)->nullable()->index();
                } else {
                    $blueprint->json('embedding')->nullable();
                }
                $blueprint->timestamp('embedding_generated_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPostgres = Schema::getConnection() instanceof PostgresConnection;
        $tables = ['blogs', 'projects', 'shares'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($isPostgres) {
                if ($isPostgres) {
                    $blueprint->dropIndex(['embedding']);
                }
                $blueprint->dropColumn(['embedding', 'embedding_generated_at']);
            });
        }
    }
};
