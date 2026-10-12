<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // [table, index name, columns]. Each is added only if it does not already exist.
    private const INDEXES = [
        ['messages', 'messages_session_created_idx', ['session_id', 'created_at']],
        ['mood_entries', 'mood_entries_user_created_idx', ['user_id', 'created_at']],
        ['session', 'session_user_status_idx', ['user_id', 'status']],
        ['exercise_runs', 'exercise_runs_user_completed_idx', ['user_id', 'status', 'completed_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $name, $columns]) {
            if (Schema::hasTable($table) && !Schema::hasIndex($table, $name)) {
                Schema::table($table, fn(Blueprint $t) => $t->index($columns, $name));
            }
        }

        // Per-language title/description, e.g. {"hi": {"title": "...", "description": "..."}}
        Schema::table('activities', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn(Blueprint $t) => $t->dropColumn('translations'));

        foreach (self::INDEXES as [$table, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn(Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
