<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define the data
        $activities = [
            [
                'activity_id' => 'reframing_01', // New field!
                'title' => 'Reframing',
                'description' => 'Learn to reframe negative thoughts into positive ones.',
                'duration' => '10 MIN',
                'icon_name' => 'psychology',
                'color_hex' => 'b578ff',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'activity_id' => 'breathing_01', // New field!
                'title' => 'Breathing',
                'description' => 'Practice deep breathing exercises to reduce stress.',
                'duration' => '5 MIN',
                'icon_name' => 'air',
                'color_hex' => '57B9FF',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ];

        // 2. Clear the table first so we don't get duplicates if you run it twice
        DB::table('activities')->truncate();

        // 3. ACTUALLY insert the data into the database!
        DB::table('activities')->insert($activities);
    }
}