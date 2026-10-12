<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per OpenAI call: spend, latency and failures become visible.
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 64);
            $table->string('model', 64);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 16)->default('ok'); // ok | error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();

            $table->index(['feature', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Server-tracked exercise progress (replaces counting in chat text).
        Schema::table('exercise_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('step')->default(0)->after('status');
            $table->json('state')->nullable()->after('step');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_runs', function (Blueprint $table) {
            $table->dropColumn(['step', 'state']);
        });
        Schema::dropIfExists('ai_usage_logs');
    }
};
