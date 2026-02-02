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
            $table->unsignedSmallInteger('read_time')->default(0)->after('meta_description');
        });

        // Backfill existing blogs
        foreach (\App\Models\Blog::all() as $blog) {
            $blog->updateQuietly([
                'read_time' => (int) ceil(str_word_count(strip_tags($blog->content ?? '')) / 200),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn('read_time');
        });
    }
};
