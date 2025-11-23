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
        Schema::table('users', function(Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->uuid('anonymous_id')->unique()->after('id');
            $table->string('role')->default('anonymous');
            $table->string('language')->default('en');
            $table->string('device_id')->nullable();
            $table->timestamp('last_active_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
