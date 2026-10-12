<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user per night. log_date is the morning the sleep ended (i.e. "last night").
        Schema::create('sleep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_run_id')->nullable()->constrained('exercise_runs')->nullOnDelete();
            $table->date('log_date');
            $table->decimal('hours', 3, 1);
            $table->unsignedTinyInteger('quality'); // 1 = poorly, 2 = okay, 3 = well
            $table->json('checklist')->nullable();  // wind-down habits ticked that evening
            $table->timestamps();

            $table->unique(['user_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sleep_logs');
    }
};
