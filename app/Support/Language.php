<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * The app's languages and how each one is used on the server.
 *
 * Codes are the app's own and are stored in users.language, so they never change: Bengali is
 * 'be' and Bhojpuri is 'bh' (the ISO codes 'bn' and 'bho' are accepted and mapped).
 *
 * Three kinds of server text:
 *  - AI-written text (chat replies, journals, push messages): the model is told the user's
 *    language, so every language is covered ({@see promptName}).
 *  - Short fixed messages (welcome, limits, payment, fallbacks): Laravel translation files in
 *    lang/<code>/ for every language ({@see text}).
 *  - Long fixed exercise scripts: written in English and Hinglish only for now, so other
 *    languages use the closest one ({@see exerciseLang}).
 */
final class Language
{
    public const DEFAULT = 'en';

    /** The app languages. */
    public const SUPPORTED = ['en', 'hi', 'mr', 'ta', 'te', 'be', 'bh'];

    private const ALIASES = ['bn' => 'be', 'bho' => 'bh'];

    /** A missing translation first tries a close language before English. */
    private const FALLBACK = ['bh' => 'hi'];

    /** How the AI is told to write. Hindi is Hinglish (Hindi in English letters) by product decision. */
    private const PROMPT_NAMES = [
        'en' => 'English',
        'hi' => 'conversational Hinglish (Latin script)',
        'mr' => 'Marathi (use Devanagari script; if the user writes Marathi in English letters, reply the same way)',
        'ta' => 'Tamil (use Tamil script; if the user writes Tamil in English letters, reply the same way)',
        'te' => 'Telugu (use Telugu script; if the user writes Telugu in English letters, reply the same way)',
        'be' => 'Bengali (use Bengali script; if the user writes Bengali in English letters, reply the same way)',
        'bh' => 'Bhojpuri (use Devanagari script; if the user writes in English letters, reply the same way)',
    ];

    /** Speech-to-text language (ISO 639-1). Bhojpuri has no own code there; Hindi is closest. */
    private const SPEECH = ['en' => 'en', 'hi' => 'hi', 'mr' => 'mr', 'ta' => 'ta', 'te' => 'te', 'be' => 'bn', 'bh' => 'hi'];

    /** A supported code for any input; unknown or empty means English. */
    public static function normalize(?string $code): string
    {
        $c = strtolower(trim((string) $code));
        $c = self::ALIASES[$c] ?? $c;

        return in_array($c, self::SUPPORTED, true) ? $c : self::DEFAULT;
    }

    public static function isSupported(?string $code): bool
    {
        $c = strtolower(trim((string) $code));

        return in_array(self::ALIASES[$c] ?? $c, self::SUPPORTED, true);
    }

    /** The language instruction for AI prompts ("LANGUAGE: Communicate in ..."). */
    public static function promptName(?string $code): string
    {
        return self::PROMPT_NAMES[self::normalize($code)];
    }

    /** The language code to send to the transcription model. */
    public static function speechCode(?string $code): string
    {
        return self::SPEECH[self::normalize($code)];
    }

    /**
     * The language of the fixed exercise scripts: 'hi' (Hinglish) for Hindi and Bhojpuri, 'en' for
     * everyone else until those scripts are translated.
     */
    public static function exerciseLang(?string $code): string
    {
        $c = self::normalize($code);

        return ($c === 'hi' || (self::FALLBACK[$c] ?? null) === 'hi') ? 'hi' : 'en';
    }

    /**
     * A fixed message from lang/<code>/<file>.php in the user's language, falling back to a close
     * language and then English.
     */
    public static function text(string $key, ?string $code, array $replace = []): string
    {
        $c = self::normalize($code);

        foreach (array_unique([$c, self::FALLBACK[$c] ?? $c, self::DEFAULT]) as $locale) {
            if (Lang::has($key, $locale, false)) {
                return trans($key, $replace, $locale);
            }
        }

        return $key;
    }

    /** A list from a translation file (e.g. push.mood), with the same fallback as {@see text}. */
    public static function lines(string $key, ?string $code): array
    {
        $c = self::normalize($code);

        foreach (array_unique([$c, self::FALLBACK[$c] ?? $c, self::DEFAULT]) as $locale) {
            $value = trans($key, [], $locale);
            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return [];
    }
}
