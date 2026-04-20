<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = [
            [
                'title' => 'Reframing',
                'description' => 'Learn to reframe negative thoughts into positive ones.',
                'duration' => '10 MIN',
                'icon_name' => 'psychology',
                'color_hex' => 'b578ff',
            ],
            [
                'title' => 'Breathing',
                'description' => 'Practice deep breathing exercises to reduce stress.',
                'duration' => '5 MIN',
                'icon_name' => 'air',
                'color_hex' => '57B9FF',
            ]
        ];
    }
}
