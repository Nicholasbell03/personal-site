<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection() instanceof PostgresConnection) {
            Schema::ensureVectorExtensionExists();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
