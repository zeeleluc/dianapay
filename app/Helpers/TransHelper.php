<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

if (!function_exists('translate')) {
    /**
     * Translates a string by assembling the full English text, then checking the locale's JSON file.
     * Placeholders are replaced before checking, and results are cached in the JSON file.
     * Skips Google API if the assembled string exists as a key in the JSON file.
     * Avoids sending strings with placeholders to the API.
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

        // Step 1: Assemble the full English string
        $assembledEnglish = applyReplacements($text, $replace);

        // Step 2: Return English string if locale is English
        if ($locale === 'en') {
            return $assembledEnglish;
        }

        // Step 3: Prepare translation file path
        $langDir = resource_path('lang');
        $filePath = "{$langDir}/{$locale}.json";

        // Ensure language directory exists
        if (!File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        // Initialize translation file
        if (!File::exists($filePath)) {
            File::put($filePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Step 4: Load translations and check for cached translation
        $translations = json_decode(File::get($filePath), true) ?? [];
        if (isset($translations[$assembledEnglish])) {
            return $translations[$assembledEnglish];
        }

        // Step 5: Validate no placeholders remain
        if (preg_match('/:[a-zA-Z0-9_]+/', $assembledEnglish)) {
            \Log::warning("Unreplaced placeholders in assembled string: {$assembledEnglish}");
            return $assembledEnglish;
        }

        // Step 6: Translate using Google API
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

            // Step 7: Cache the translation in the JSON file
            $translations[$assembledEnglish] = $translated;
            File::put($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            \Log::error("Translation failed for '{$assembledEnglish}': {$e->getMessage()}");
            return $assembledEnglish;
        }

        return $translated;
    }
}

/**
 * Replaces placeholders in a string with provided values.
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
