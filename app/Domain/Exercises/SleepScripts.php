<?php

namespace App\Domain\Exercises;

/**
 * The words of the Sleep Wind-down session: a body scan or a short story, in English and Hinglish.
 * The app shows (and, if the user wants, reads aloud) one line at a time; each line has a fixed
 * time slot sized to its length so the pace stays slow and even.
 */
class SleepScripts
{
    public const BODY = 'body';
    public const STORY = 'story';

    private const SCRIPTS = [
        'en' => [
            self::BODY => [
                "Lie down in a comfortable position, and let your arms rest by your sides.",
                "Close your eyes if you like. Breathe in slowly through your nose, and let it go with a long, soft sigh.",
                "Let's begin at your feet. Notice them, and let them grow heavy and warm.",
                "Let that softness travel up through your ankles and calves. Your legs are heavy, and nothing needs to be done.",
                "Relax your knees and thighs. Let the bed hold the full weight of your legs.",
                "Soften your hips and lower back. Let them sink into the mattress.",
                "Feel your belly rise and fall, slow and easy, like gentle waves.",
                "Let your chest open and soften. Your heart can beat quietly.",
                "Release your shoulders. Let them drop away from your ears.",
                "Let that heaviness flow down your arms, through your elbows and wrists, all the way to your fingertips.",
                "Soften your neck, and the back of your head, resting gently.",
                "Relax your jaw. Let your tongue rest. Smooth your forehead, and soften the space between your eyes.",
                "Your whole body is heavy and calm. If thoughts come, let them drift by like clouds.",
                "Breathe in slowly... and out... Each breath carries you a little deeper into rest.",
                "There is nothing to do and nowhere to be. You are safe, and you are allowed to rest.",
                "Stay here as long as you like. Goodnight.",
            ],
            self::STORY => [
                "Imagine a quiet lake at the end of a long, warm day.",
                "The sun is low, and the water glows with soft gold light.",
                "You are walking slowly along a path of cool, smooth stones. There is no hurry at all.",
                "A gentle breeze moves through the tall grass, and you hear it whisper as you pass.",
                "At the water's edge there is a small wooden boat, rocking just a little.",
                "You step in, and sit down. The boat holds you easily, as if it was waiting for you.",
                "It drifts out slowly. The ripples spread wide and soft around you.",
                "The sky turns from gold to rose, and then to deep, quiet blue.",
                "One by one, the first stars appear, small and calm.",
                "The water hums a low, slow song, and your body feels light and warm.",
                "With every ripple, the day grows a little further away.",
                "You are floating, safe and still, under a sky full of stars.",
                "Let your eyes grow heavy. The lake will carry you the rest of the way. Goodnight.",
            ],
        ],
        'hi' => [
            self::BODY => [
                "Aaram se letiye, aur apne haath apne paas dheele chhod dijiye.",
                "Chahein to aankhein band kar lijiye. Naak se dheere se saans andar lijiye, aur lambi, narm saans ke saath chhod dijiye.",
                "Pairon se shuru karte hain. Unhe mehsoos kijiye, aur unhe bhaari aur garam hone dijiye.",
                "Yeh narmi takhnon aur pindliyon tak jaane dijiye. Aapki taangein bhaari hain, aur kuch karne ki zaroorat nahi.",
                "Ghutne aur jaanghein dheeli chhod dijiye. Bistar ko aapki taangon ka poora bhaar sambhalne dijiye.",
                "Kulhe aur kamar ke nichle hisse ko narm kijiye. Unhe gaddhe mein dhansne dijiye.",
                "Apne pet ko oopar neeche hote mehsoos kijiye, dheere aur aasaan, halki lehron ki tarah.",
                "Seene ko khulne aur narm hone dijiye. Dil ko shaanti se dhadakne dijiye.",
                "Kandhe dheele chhod dijiye. Unhe kaanon se door neeche girne dijiye.",
                "Yeh bhaaripan baahon se bahta hua, kohni aur kalaai se hote hue, ungliyon ke siron tak pahunchne dijiye.",
                "Gardan ko narm kijiye, aur sir ke pichhle hisse ko aaram se tikne dijiye.",
                "Jabda dheela kijiye. Jeebh ko aaram dijiye. Maathe ko chikna kijiye, aur bhauhon ke beech ki jagah ko narm kijiye.",
                "Aapka poora sharir bhaari aur shaant hai. Agar vichaar aayein, to unhe badalon ki tarah guzar jaane dijiye.",
                "Dheere se saans andar... aur bahar... Har saans aapko aaram mein thoda aur gehra le jaati hai.",
                "Kuch karne ko nahi hai, aur kahin jaana nahi hai. Aap surakshit hain, aur aapko aaram karne ki ijaazat hai.",
                "Jab tak chahein yahin rahiye. Shubh ratri.",
            ],
            self::STORY => [
                "Kalpana kijiye ek shaant jheel, ek lambe, garam din ke ant mein.",
                "Suraj neeche hai, aur paani narm sunehri roshni se chamak raha hai.",
                "Aap thandi, chikni patharon ki pagdandi par dheere dheere chal rahe hain. Koi jaldi nahi hai.",
                "Ek halki hawa lambi ghaas mein se guzarti hai, aur aap use fusfusate hue sunte hain.",
                "Paani ke kinare ek chhoti lakdi ki naav hai, jo bas thodi si hil rahi hai.",
                "Aap usmein kadam rakhte hain aur baith jaate hain. Naav aapko aaraam se sambhal leti hai, jaise woh aapka intezaar kar rahi thi.",
                "Woh dheere se bahar behti hai. Lehrein aapke aas paas door tak narmi se failti hain.",
                "Aasman sunehre se gulabi, aur phir gehre, shaant neele rang mein badal jaata hai.",
                "Ek ek karke pehle taare nikalte hain, chhote aur shaant.",
                "Paani ek dheeri, narm dhun gungunata hai, aur aapka sharir halka aur garam mehsoos karta hai.",
                "Har lehar ke saath, din thoda aur door hota jaata hai.",
                "Aap taaron se bhare aasman ke neeche, surakshit aur shaant, tair rahe hain.",
                "Apni aankhon ko bhaari hone dijiye. Baaki ka safar jheel aapko karwa degi. Shubh ratri.",
            ],
        ],
    ];

    /** @return array<int, array{text: string, seconds: int}> */
    public static function lines(string $mode, string $lang): array
    {
        $set = self::SCRIPTS[$lang] ?? self::SCRIPTS['en'];
        $mode = isset($set[$mode]) ? $mode : self::BODY;

        return array_map(fn($text) => ['text' => $text, 'seconds' => self::slot($text)], $set[$mode]);
    }

    public static function totalSeconds(array $lines): int
    {
        return (int) array_sum(array_column($lines, 'seconds'));
    }

    /** Time for a line: slow read-aloud time plus a pause, never under 7 seconds. */
    private static function slot(string $text): int
    {
        $words = str_word_count(strip_tags($text), 0, 'àáâãäåæçèéêëìíîïñòóôõöùúûüýÿ');
        return max(7, (int) ceil($words / 1.8) + 4);
    }
}
