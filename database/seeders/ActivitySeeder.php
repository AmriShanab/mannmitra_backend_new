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
                'translations' => json_encode(['hi' => ['title' => 'रीफ़्रेमिंग', 'description' => 'नकारात्मक विचारों को संतुलित नज़रिए में बदलना सीखें।']]),
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
                'translations' => json_encode(['hi' => ['title' => 'साँस की एक्सरसाइज़', 'description' => 'तनाव कम करने के लिए गहरी साँस लेने का अभ्यास करें।']]),
                'description' => 'Practice deep breathing exercises to reduce stress.',
                'duration' => '5 MIN',
                'icon_name' => 'air',
                'color_hex' => '57B9FF',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'activity_id' => 'grounding_01',
                'title' => 'Grounding',
                'translations' => json_encode(['hi' => ['title' => 'ग्राउंडिंग', 'description' => '5-4-3-2-1 तकनीक से चिंता भरे विचारों को शांत करें।']]),
                'description' => 'Calm anxious thoughts with the 5-4-3-2-1 senses technique.',
                'duration' => '5 MIN',
                'icon_name' => 'awesome',
                'color_hex' => '4CC9A6',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'activity_id' => 'butterfly_hug_01',
                'title' => 'Butterfly Hug',
                'translations' => json_encode(['hi' => ['title' => 'बटरफ्लाई हग', 'description' => 'सीने पर हाथ क्रॉस करके बारी-बारी से थपथपाएँ और धीरे-धीरे साँस लें।']]),
                'description' => 'Cross your arms, tap left and right, and breathe slowly for a minute.',
                'duration' => '1 MIN',
                'icon_name' => 'favorite',
                'color_hex' => 'F28CB1',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'activity_id' => 'gratitude_01',
                'title' => 'Gratitude Journal',
                'translations' => json_encode(['hi' => ['title' => 'आभार डायरी', 'description' => 'आज की 3 अच्छी बातें लिखें। हम उन्हें सहेजकर बाद में आपको दिखाएँगे।']]),
                'description' => 'Note 3 good things from today. We save them and show them back to you later.',
                'duration' => '3 MIN',
                'icon_name' => 'sunny',
                'color_hex' => 'FFC857',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'activity_id' => 'sleep_winddown_01',
                'title' => 'Sleep Wind-down',
                'translations' => json_encode(['hi' => ['title' => 'नींद की तैयारी', 'description' => 'चेकलिस्ट, बॉडी स्कैन या नींद की कहानी, और एक आसान स्लीप लॉग।']]),
                'description' => 'A short checklist, a body scan or sleep story, and a simple sleep log.',
                'duration' => '8 MIN',
                'icon_name' => 'bedtime',
                'color_hex' => '7C83FD',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        // 2. Clear the table first so we don't get duplicates if you run it twice
        DB::table('activities')->truncate();

        // 3. ACTUALLY insert the data into the database!
        DB::table('activities')->insert($activities);

        // The catalog is cached for a few minutes; refresh it right away.
        \Illuminate\Support\Facades\Cache::forget(\App\Services\ActivityService::CACHE_KEY);
    }
}