<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes the public schema through its REST API (PostgREST) to anyone holding the
 * publishable/anon key. Tables without row level security are readable and writable that way,
 * which included user_contexts (the agent system prompt) and agent_conversations (visitor IPs).
 *
 * Enabling RLS with no policies denies the anon/authenticated roles entirely. Laravel connects as
 * the postgres role, which bypasses RLS, so the app is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::table('pg_tables')
            ->where('schemaname', 'public')
            ->where('rowsecurity', false)
            ->pluck('tablename');

        foreach ($tables as $table) {
            DB::statement(sprintf('alter table public.%s enable row level security', DB::getQueryGrammar()->wrap($table)));
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: disabling RLS would re-expose these tables via the Data API.
    }
};
