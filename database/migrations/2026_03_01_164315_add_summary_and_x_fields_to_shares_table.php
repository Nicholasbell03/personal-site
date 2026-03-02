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
        Schema::table('shares', function (Blueprint $table) {
            $table->string('summary', 280)->nullable()->after('commentary');
            $table->boolean('post_to_x')->default(true)->after('og_raw');
            $table->string('x_post_id')->nullable()->after('post_to_x');
        });
    }

    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropColumn(['summary', 'post_to_x', 'x_post_id']);
        });
    }
};
