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
        Schema::create('crisis_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('session')->onDelete('cascade');
            $table->string('trigger_keyword');
            $table->string('severity')->default('high');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crisis_alerts');
    }
};
