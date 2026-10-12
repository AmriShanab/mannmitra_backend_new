<?php

namespace App\Services;

use App\Interfaces\CrisisRepositoryInterface;
use Illuminate\Support\Str;

class CrisisService
{

    protected $crisisRepo;

    protected $dangerKeywords = [
        'suicide', 'kill myself', 'end my life', 'want to die', 'overdose',
        'cutting myself', 'hang myself', 'no reason to live',
        
        // Hinglish / Hindi
        'mar jaunga', 'mar jaungi', 'khatam karna', 'zindagi bekar hai',
        'आत्महत्या', 'marne ka mann'
    ];

    public function __construct(CrisisRepositoryInterface $crisisRepo)
    {
        $this->crisisRepo = $crisisRepo;
    }

    public function detectCrisis($text)
    {
        $normalizedText = Str::lower($text);

        foreach ($this->dangerKeywords as $key => $words) {
            if(Str::contains($normalizedText,$words)){
                return $words;
            }
        }

        return null;
    }

    public function logCrisis($sessionId, $words, $severity = 'high')
    {
        $this->crisisRepo->logAlert($sessionId, $words, $severity);
    }

    public function getCrisisResponse()
    {
        return "I'm really concerned about what you just said. You are not alone, and there is help available.\n\n" .
               "Please reach out to these support lines in India immediately:\n" .
               "📞 **iCall:** 9152987821 (Mon-Sat, 10 AM - 8 PM)\n" .
               "📞 **AASRA:** 9820466726 (24x7)\n" .
               "📞 **Vandrevala Foundation:** 1860 266 2345 (24x7)\n\n" .
               "I am here to listen, but please consider calling one of these numbers right now.";
    }
}