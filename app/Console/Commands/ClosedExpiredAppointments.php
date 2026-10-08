<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClosedExpiredAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'closed:expired:appointments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        $appointments = Appointment::where('scheduled_at', '<', $today)
                                    ->whereIn('status', ['pending', 'confirmed'])
                                    ->update(['status' => 'closed']);

        // Bookings whose payment was never completed (abandoned checkout) free themselves up.
        $abandoned = Appointment::where('status', 'pending_payment')
                                    ->where('created_at', '<', now()->subHours(2))
                                    ->update(['status' => 'cancelled']);

        $this->info("Successfully expired {$appointments} old Appointments, cancelled {$abandoned} unpaid bookings");
    }
}
