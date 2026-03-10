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
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('x_post_id')->nullable();
            $table->string('linkedin_post_id')->nullable();
            $table->boolean('post_to_x')->default(true);
            $table->boolean('post_to_linkedin')->default(true);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('x_post_id')->nullable();
            $table->string('linkedin_post_id')->nullable();
            $table->boolean('post_to_x')->default(true);
            $table->boolean('post_to_linkedin')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn(['x_post_id', 'linkedin_post_id', 'post_to_x', 'post_to_linkedin']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['x_post_id', 'linkedin_post_id', 'post_to_x', 'post_to_linkedin']);
        });
    }
};
