<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saved output of the reframing exercise. Free-text columns are encrypted by the model.
        Schema::create('thought_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_run_id')->nullable()->constrained('exercise_runs')->nullOnDelete();
            $table->text('situation');
            $table->text('automatic_thought');
            $table->string('distortion', 40)->nullable();
            $table->unsignedTinyInteger('intensity_before');
            $table->text('evidence_against');
            $table->text('balanced_thought');
            $table->unsignedTinyInteger('intensity_after');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thought_records');
    }
};
