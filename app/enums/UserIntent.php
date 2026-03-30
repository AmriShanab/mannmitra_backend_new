<?php

namespace App\Enums;

class UserIntent
{
    public const CRISIS = 'crisis';
    public const VENTING_SAD = 'venting_sad';
    public const HAPPY_CASUAL = 'happy_casual';
    public const JOURNALING = 'journaling';
    public const NEEDS_DISTRACTION = 'needs_distraction';

    public static function all(): array
    {
        return [
            self::CRISIS,
            self::VENTING_SAD,
            self::HAPPY_CASUAL,
            self::JOURNALING,
            self::NEEDS_DISTRACTION,
        ];
    }
}

?>