<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Booking saves a new appointment as "pending_payment" until Razorpay confirms it, and the
     * scheduler/join logic use "closed" and "expired". The original enum rejected all three.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY status ENUM('pending_payment','pending','confirmed','completed','cancelled','closed','expired') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE appointments SET status = 'cancelled' WHERE status IN ('pending_payment','closed','expired')");
        DB::statement("ALTER TABLE appointments MODIFY status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
