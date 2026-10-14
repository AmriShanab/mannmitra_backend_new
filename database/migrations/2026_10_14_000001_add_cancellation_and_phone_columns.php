<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Used to let patients call their psychiatrist to ask for a cancellation.
            $table->string('phone', 20)->nullable();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('refund_id')->nullable();
            $table->string('refund_status')->nullable(); // initiated | failed
            $table->timestamp('refund_initiated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['cancel_requested_at', 'cancelled_at', 'refund_id', 'refund_status', 'refund_initiated_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
