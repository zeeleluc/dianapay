<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

if (!function_exists('translate')) {
    function translate(string $text, array $replace = []): string
    {
        $locale = App::getLocale();

        // If English, return original with replacements applied (if any)
        if ($locale === 'en') {
            if ($replace) {
                foreach ($replace as $key => $value) {
                    $text = str_replace(':'.$key, $value, $text);
                }
            }
            return $text;
        }

        $langDir = resource_path('lang');
        $filePath = "{$langDir}/{$locale}.json";

        // Ensure lang directory exists
        if (!File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        // Create empty JSON file if it doesn't exist
        if (!File::exists($filePath)) {
            File::put($filePath, json_encode(new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Load existing translations
        $translations = json_decode(File::get($filePath), true) ?? [];

        // Find translation or fallback to $text
        $translated = $translations[$text] ?? $text;

        // Apply replacements if provided
        if ($replace) {
            foreach ($replace as $key => $value) {
                $translated = str_replace(':'.$key, $value, $translated);
            }
        }

        // Save new translation if missing (only the base text, without replacements)
        if (!isset($translations[$text])) {
            // Translate via Google Translate (free endpoint)
            $response = Http::get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl'     => 'en',
                'tl'     => $locale,
                'dt'     => 't',
                'q'      => $text,
            ]);

            $autoTranslated = $response->json()[0][0][0] ?? $text;

            // Save the base translation without replacements (placeholders remain)
            $translations[$text] = $autoTranslated;
            File::put($filePath, json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // Apply replacements on auto translated string
            foreach ($replace as $key => $value) {
                $autoTranslated = str_replace(':'.$key, $value, $autoTranslated);
            }

            return $autoTranslated;
        }

        return $translated;
    }
}
