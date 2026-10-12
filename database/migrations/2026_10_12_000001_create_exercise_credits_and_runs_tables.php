<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per paid pack (e.g. 5 sessions of one exercise).
        Schema::create('exercise_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity_id');
            $table->string('razorpay_order_id')->unique();
            $table->string('transaction_id')->nullable();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('sessions_total');
            $table->unsignedInteger('sessions_used')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'activity_id']);
        });

        // One row per exercise attempt; a session is consumed only when it completes.
        Schema::create('exercise_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity_id');
            $table->foreignId('exercise_credit_id')->nullable()->constrained('exercise_credits')->nullOnDelete();
            $table->string('status')->default('started'); // started | completed | abandoned
            $table->boolean('free_pass')->default(false); // subscriber: no credit consumed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_runs');
        Schema::dropIfExists('exercise_credits');
    }
};
