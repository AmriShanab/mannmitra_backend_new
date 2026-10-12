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
                'translations' => json_encode(['hi' => ['title' => 'रीफ़्रेमिंग', 'description' => 'नकारात्मक विचारों को संतुलित नज़रिए में बदलना सीखें।'], 'mr' => ['title' => 'रीफ्रेमिंग', 'description' => 'नकारात्मक विचारांकडे संतुलित नजरेने पाहायला शिका.'], 'ta' => ['title' => 'மறுவடிவமைத்தல்', 'description' => 'எதிர்மறை எண்ணங்களைச் சமநிலையான பார்வையாக மாற்றக் கற்றுக்கொள்ளுங்கள்.'], 'te' => ['title' => 'రీఫ్రేమింగ్', 'description' => 'ప్రతికూల ఆలోచనలను సమతుల్య దృక్పథంగా మార్చడం నేర్చుకోండి.'], 'be' => ['title' => 'রিফ্রেমিং', 'description' => 'নেতিবাচক চিন্তাকে ভারসাম্যপূর্ণ দৃষ্টিভঙ্গিতে বদলাতে শিখুন।'], 'bh' => ['title' => 'रीफ्रेमिंग', 'description' => 'नकारात्मक विचार के संतुलित नजर से देखे के सीखीं।']]),
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
                'translations' => json_encode(['hi' => ['title' => 'साँस की एक्सरसाइज़', 'description' => 'तनाव कम करने के लिए गहरी साँस लेने का अभ्यास करें।'], 'mr' => ['title' => 'श्वासाचा व्यायाम', 'description' => 'ताण कमी करण्यासाठी दीर्घ श्वसनाचा सराव करा.'], 'ta' => ['title' => 'மூச்சுப் பயிற்சி', 'description' => 'மன அழுத்தத்தைக் குறைக்க ஆழ்ந்த மூச்சுப் பயிற்சி செய்யுங்கள்.'], 'te' => ['title' => 'శ్వాస వ్యాయామం', 'description' => 'ఒత్తిడి తగ్గించడానికి లోతైన శ్వాసను సాధన చేయండి.'], 'be' => ['title' => 'শ্বাসের ব্যায়াম', 'description' => 'চাপ কমাতে গভীর শ্বাসের অনুশীলন করুন।'], 'bh' => ['title' => 'साँस के अभ्यास', 'description' => 'तनाव कम करे खातिर गहिर साँस लेवे के अभ्यास करीं।']]),
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
                'translations' => json_encode(['hi' => ['title' => 'ग्राउंडिंग', 'description' => '5-4-3-2-1 तकनीक से चिंता भरे विचारों को शांत करें।'], 'mr' => ['title' => 'ग्राउंडिंग', 'description' => '5-4-3-2-1 तंत्राने चिंतेचे विचार शांत करा.'], 'ta' => ['title' => 'கிரவுண்டிங்', 'description' => '5-4-3-2-1 முறையால் பதற்றமான எண்ணங்களை அமைதிப்படுத்துங்கள்.'], 'te' => ['title' => 'గ్రౌండింగ్', 'description' => '5-4-3-2-1 పద్ధతితో ఆందోళన ఆలోచనలను శాంతపరచండి.'], 'be' => ['title' => 'গ্রাউন্ডিং', 'description' => '৫-৪-৩-২-১ কৌশলে উদ্বেগের চিন্তা শান্ত করুন।'], 'bh' => ['title' => 'ग्राउंडिंग', 'description' => '5-4-3-2-1 तरीका से चिंता वाला विचार शांत करीं।']]),
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
                'translations' => json_encode(['hi' => ['title' => 'बटरफ्लाई हग', 'description' => 'सीने पर हाथ क्रॉस करके बारी-बारी से थपथपाएँ और धीरे-धीरे साँस लें।'], 'mr' => ['title' => 'बटरफ्लाय हग', 'description' => 'छातीवर हात क्रॉस करा, डावीकडे-उजवीकडे थोपटा आणि एक मिनिट हळू श्वास घ्या.'], 'ta' => ['title' => 'பட்டாம்பூச்சி அணைப்பு', 'description' => 'கைகளை மார்பில் குறுக்காக வைத்து, இடது வலதாகத் தட்டி, ஒரு நிமிடம் மெதுவாக மூச்சு விடுங்கள்.'], 'te' => ['title' => 'బటర్‌ఫ్లై హగ్', 'description' => 'చేతులను ఛాతీపై అడ్డంగా పెట్టి, ఎడమ-కుడి తట్టుతూ, ఒక నిమిషం నెమ్మదిగా శ్వాస తీసుకోండి.'], 'be' => ['title' => 'বাটারফ্লাই হাগ', 'description' => 'বুকে হাত আড়াআড়ি রেখে বাম-ডানে আলতো চাপড় দিন, আর এক মিনিট ধীরে শ্বাস নিন।'], 'bh' => ['title' => 'बटरफ्लाई हग', 'description' => 'छाती पर हाथ क्रॉस करीं, बाएँ-दाएँ थपथपाईं आ एक मिनट धीरे-धीरे साँस लीं।']]),
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
                'translations' => json_encode(['hi' => ['title' => 'आभार डायरी', 'description' => 'आज की 3 अच्छी बातें लिखें। हम उन्हें सहेजकर बाद में आपको दिखाएँगे।'], 'mr' => ['title' => 'कृतज्ञता डायरी', 'description' => 'आजच्या 3 चांगल्या गोष्टी लिहा. आम्ही त्या जपून ठेवू आणि नंतर तुम्हाला दाखवू.'], 'ta' => ['title' => 'நன்றியுணர்வு நாட்குறிப்பு', 'description' => 'இன்றைய 3 நல்ல விஷயங்களை எழுதுங்கள். அவற்றைச் சேமித்து பின்னர் உங்களுக்குக் காட்டுவோம்.'], 'te' => ['title' => 'కృతజ్ఞతా జర్నల్', 'description' => 'ఈ రోజు 3 మంచి విషయాలు రాయండి. వాటిని దాచి తర్వాత మీకు చూపిస్తాం.'], 'be' => ['title' => 'কৃতজ্ঞতার ডায়েরি', 'description' => 'আজকের ৩টি ভালো কথা লিখুন। আমরা সেগুলো রেখে দেব আর পরে আপনাকে দেখাব।'], 'bh' => ['title' => 'आभार डायरी', 'description' => 'आज के 3 गो नीमन बात लिखीं। हमनी ओकरा के सहेज के बाद में रउआ के देखाइब।']]),
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
                'translations' => json_encode(['hi' => ['title' => 'नींद की तैयारी', 'description' => 'चेकलिस्ट, बॉडी स्कैन या नींद की कहानी, और एक आसान स्लीप लॉग।'], 'mr' => ['title' => 'झोपेची तयारी', 'description' => 'छोटी चेकलिस्ट, बॉडी स्कॅन किंवा झोपेची गोष्ट, आणि सोपी झोप नोंद.'], 'ta' => ['title' => 'தூக்கத்திற்குத் தயாராகுதல்', 'description' => 'ஒரு சிறிய சரிபார்ப்புப் பட்டியல், உடல் ஸ்கேன் அல்லது தூக்கக் கதை, மற்றும் எளிய தூக்கப் பதிவு.'], 'te' => ['title' => 'నిద్రకు సిద్ధం', 'description' => 'చిన్న చెక్‌లిస్ట్, బాడీ స్కాన్ లేదా నిద్ర కథ, మరియు సులభమైన నిద్ర లాగ్.'], 'be' => ['title' => 'ঘুমের প্রস্তুতি', 'description' => 'ছোট একটি চেকলিস্ট, বডি স্ক্যান বা ঘুমের গল্প, আর সহজ ঘুমের খাতা।'], 'bh' => ['title' => 'नींद के तइयारी', 'description' => 'छोट चेकलिस्ट, बॉडी स्कैन भा नींद के कहानी, आ आसान नींद के रिकॉर्ड।']]),
                'description' => 'A short checklist, a body scan or sleep story, and a simple sleep log.',
                'duration' => '8 MIN',
                'icon_name' => 'bedtime',
                'color_hex' => '7C83FD',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'activity_id' => 'values_goal_01',
                'title' => 'Values & Goals',
                'translations' => json_encode(['hi' => ['title' => 'मूल्य और लक्ष्य', 'description' => 'अपने मूल्य चुनें, उन्हें एक छोटे साप्ताहिक लक्ष्य में बदलें, और प्रगति पर चेक-इन करें।'], 'mr' => ['title' => 'मूल्ये आणि उद्दिष्टे', 'description' => 'तुमच्यासाठी जे महत्त्वाचे आहे ते निवडा, त्याचे छोटे साप्ताहिक उद्दिष्ट करा आणि प्रगती तपासा.'], 'ta' => ['title' => 'மதிப்புகள் & இலக்குகள்', 'description' => 'உங்களுக்கு முக்கியமானதைத் தேர்ந்தெடுத்து, ஒரு சிறிய வார இலக்காக மாற்றி, முன்னேற்றத்தைச் சரிபாருங்கள்.'], 'te' => ['title' => 'విలువలు & లక్ష్యాలు', 'description' => 'మీకు ముఖ్యమైనదాన్ని ఎంచుకుని, దాన్ని చిన్న వారపు లక్ష్యంగా మార్చి, పురోగతిని తనిఖీ చేయండి.'], 'be' => ['title' => 'মূল্যবোধ ও লক্ষ্য', 'description' => 'আপনার কাছে যা জরুরি তা বেছে নিন, সেটিকে সপ্তাহের একটি ছোট লক্ষ্যে বদলান, আর অগ্রগতি দেখুন।'], 'bh' => ['title' => 'मूल्य आ लक्ष्य', 'description' => 'जवन रउआ खातिर जरूरी बा ओकरा के चुनीं, हफ्ता के एगो छोट लक्ष्य बनाईं, आ प्रगति जाँचीं।']]),
                'description' => 'Pick what matters most, turn it into one small weekly goal, and check in on your progress.',
                'duration' => '5 MIN',
                'icon_name' => 'flag',
                'color_hex' => '4CB8A6',
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