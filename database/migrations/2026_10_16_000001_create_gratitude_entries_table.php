<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The three good things from a finished Gratitude Journal session. `items` is encrypted by the model.
        Schema::create('gratitude_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_run_id')->nullable()->constrained('exercise_runs')->nullOnDelete();
            $table->date('entry_date');
            $table->text('items');
            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gratitude_entries');
    }
};
