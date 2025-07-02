<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Cache translated highlight words for the current locale.
 */
function getHighlightWords(): array
{
    static $words = null;

    if ($words === null) {
        $locale = App::getLocale();

        // Base English words to highlight
        $baseWords = [
            'crypto',
            'payments',
            'monocurrency',
            'cryptocurrency',
            'cryptocurrencies',
            'anonymous',
        ];

        if ($locale === 'en') {
            $words = $baseWords;
        } else {
            // Translate highlight words into the target locale
            $words = [];
            foreach ($baseWords as $word) {
                $translatedWord = translateText($word, [], false); // No highlighting during translation
                $words[] = $translatedWord;
            }
        }
    }

    return $words;
}

/**
 * Core translation function.
 * @param bool $applyHighlight Whether to highlight words after translation.
 */
function translateText(string $text, array $replace = [], bool $applyHighlight = true): string
{
    $locale = App::getLocale();

    // Handle exceptions (predefined translations)
    $exceptions = [
        'passwords.user'      => "We can't find a user with that email address.",
        'validation.required' => 'The :attribute field is required.',
        'validation.string'   => 'The :attribute must be a string.',
        'auth.password'       => 'The provided password is incorrect.',
    ];

    if (isset($exceptions[$text])) {
        $text = $exceptions[$text];
    }

    // Apply replacements (e.g., :attribute)
    $assembledEnglish = applyReplacements($text, $replace);

    // Return early for English, with optional highlighting
    if ($locale === 'en') {
        return $applyHighlight ? highlightWords($assembledEnglish) : $assembledEnglish;
    }

    // Load or create translation file
    $langDir = resource_path('lang');
    $filePath = "{$langDir}/{$locale}.json";

    if (!File::exists($langDir)) {
        File::makeDirectory($langDir, 0755, true);
    }

    if (!File::exists($filePath)) {
        File::put($filePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $translations = json_decode(File::get($filePath), true) ?? [];

    // Check if translation exists
    if (isset($translations[$assembledEnglish])) {
        return $applyHighlight ? highlightWords($translations[$assembledEnglish]) : $translations[$assembledEnglish];
    }

    // Warn about unreplaced placeholders
    if (preg_match('/:[a-zA-Z0-9_]+/', $assembledEnglish)) {
        \Log::warning("Unreplaced placeholders in assembled string: {$assembledEnglish}");
        return $applyHighlight ? highlightWords($assembledEnglish) : $assembledEnglish;
    }

    // Fetch translation from Google API
    try {
        $response = Http::get('https://translate.googleapis.com/translate_a/single', [
            'client' => 'gtx',
            'sl'     => 'en',
            'tl'     => $locale,
            'dt'     => 't',
            'q'      => $assembledEnglish,
        ]);

        $translated = $assembledEnglish;

        if ($response->successful()) {
            $responseData = $response->json();
            if (isset($responseData[0][0][0]) && is_string($responseData[0][0][0])) {
                $translated = $responseData[0][0][0];
            }
        }

        // Cache the translation
        $translations[$assembledEnglish] = $translated;
        File::put($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (\Exception $e) {
        \Log::error("Translation failed for '{$assembledEnglish}': {$e->getMessage()}");
        return $applyHighlight ? highlightWords($assembledEnglish) : $assembledEnglish;
    }

    return $applyHighlight ? highlightWords($translated) : $translated;
}

/**
 * Public translation function (with highlighting by default).
 */
if (!function_exists('translate')) {
    function translate(string $text, array $replace = []): string
    {
        return translateText($text, $replace, true);
    }
}

/**
 * Replace placeholders with values.
 */
function applyReplacements(string $text, array $replace): string
{
    foreach ($replace as $key => $value) {
        $text = str_replace(':' . $key, $value, $text);
    }
    return $text;
}

/**
 * Highlight words by wrapping them in a span with color.
 */
function highlightWords(string $text): string
{
    $highlightWords = getHighlightWords();

    foreach ($highlightWords as $word) {
        $patternWord = preg_quote($word, '/');
        $patternWord = str_replace(' ', '\s+', $patternWord);

        $text = preg_replace_callback(
            '/\b(' . $patternWord . ')\b/i',
            fn($matches) => '<span style="color: #FACC14">' . $matches[1] . '</span>',
            $text
        );
    }

    return $text;
}
