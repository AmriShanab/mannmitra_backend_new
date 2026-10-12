<?php

namespace App\Enums;

class UserIntent
{
    public const CRISIS = 'crisis';
    public const VENTING_SAD = 'venting_sad';
    public const HAPPY_CASUAL = 'happy_casual';
    public const JOURNALING = 'journaling';
    public const NEEDS_DISTRACTION = 'needs_distraction';
    public const CBT_BREATHING = 'cbt_breathing';
    public const CBT_GROUNDING = 'cbt_grounding';
    public const CBT_REFRAMING = 'cbt_reframing';

    public static function all(): array
    {
        return [
            self::CRISIS,
            self::VENTING_SAD,
            self::HAPPY_CASUAL,
            self::JOURNALING,
            self::NEEDS_DISTRACTION,
            self::CBT_BREATHING,
            self::CBT_GROUNDING,
            self::CBT_REFRAMING,
        ];
    }
}

?>