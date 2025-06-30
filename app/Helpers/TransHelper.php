<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

if (!function_exists('translate')) {
    /**
     * Translates a string by first assembling the full English text, then translating it to the target locale.
     * Placeholders (e.g., :attribute) are replaced before translation, and the result is cached.
     * Ensures no strings with placeholders are cached or sent to the translation API.
     *
     * @param string $text The input string with optional placeholders (e.g., "The :attribute field is required.")
     * @param array $replace Key-value pairs for placeholder replacements (e.g., ['attribute' => 'email'])
     * @return string The translated string or the assembled English string if translation fails
     */
    function translate(string $text, array $replace = []): string
    {
        $locale = App::getLocale();

        $exceptions = [
            'passwords.user'      => "We can't find a user with that email address.",
            'validation.required' => 'The :attribute field is required.',
            'validation.string'   => 'The :attribute must be a string.',
            'auth.password'       => 'The provided password is incorrect.',
        ];

        if (isset($exceptions[$text])) {
            $text = $exceptions[$text];
        }

        // Step 1: Assemble the full English string by replacing placeholders
        $assembledEnglish = applyReplacements($text, $replace);

        // Step 2: Return the assembled English string if the locale is English
        if ($locale === 'en') {
            return $assembledEnglish;
        }

        // Step 3: Prepare translation cache path
        $langDir = resource_path('lang');
        $filePath = "{$langDir}/{$locale}.json";

        // Ensure the language directory exists
        if (!File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        // Initialize the translation file if it doesn't exist
        if (!File::exists($filePath)) {
            File::put($filePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Step 4: Load existing translations
        $translations = json_decode(File::get($filePath), true) ?? [];

        // Step 5: Return cached translation if it exists
        if (isset($translations[$assembledEnglish])) {
            return $translations[$assembledEnglish];
        }

        // Step 6: Validate that the assembled string has no placeholders
        if (preg_match('/:[a-zA-Z]+/', $assembledEnglish)) {
            // Log error and return the assembled English string as a fallback
            \Log::warning("Unreplaced placeholders found in assembled string: {$assembledEnglish}");
            return $assembledEnglish;
        }

        // Step 7: Translate the fully assembled English string (no placeholders)
        try {
            $response = Http::get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl'     => 'en',
                'tl'     => $locale,
                'dt'     => 't',
                'q'      => $assembledEnglish,
            ]);

            // Extract the translated text or fallback to the English string
            $translated = $response->successful() && isset($response->json()[0][0][0])
                ? $response->json()[0][0][0]
                : $assembledEnglish;

            // Step 8: Cache the translation using the assembled English string as the key
            $translations[$assembledEnglish] = $translated;
            File::put($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            // Log the error and return the assembled English string as a fallback
            \Log::error("Translation failed for '{$assembledEnglish}': {$e->getMessage()}");
            return $assembledEnglish;
        }

        // Step 9: Return the translated string
        return $translated;
    }
}

/**
 * Replaces placeholders (e.g., :attribute) in a string with provided values.
 *
 * @param string $text The input string with placeholders
 * @param array $replace Key-value pairs for replacements
 * @return string The string with placeholders replaced
 */
function applyReplacements(string $text, array $replace): string
{
    foreach ($replace as $key => $value) {
        $text = str_replace(':' . $key, $value, $text);
    }
    return $text;
}
