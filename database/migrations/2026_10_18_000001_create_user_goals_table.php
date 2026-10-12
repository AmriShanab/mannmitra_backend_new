<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One small weekly goal at a time per user; older ones stay as history.
        Schema::create('user_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_run_id')->nullable()->constrained('exercise_runs')->nullOnDelete();
            $table->json('core_values');                    // up to 3 value ids the user picked
            $table->string('focus_value', 32);              // the value this goal serves
            $table->text('goal_text');                      // encrypted (personal)
            $table->unsignedTinyInteger('confidence')->nullable(); // 1 low, 2 fair, 3 high
            $table->string('status', 16)->default('active'); // active | completed | replaced
            $table->string('progress', 16)->nullable();      // done | partly | not (latest check-in)
            $table->date('due_date');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_goals');
    }
};
