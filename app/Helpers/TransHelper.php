<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

if (!function_exists('translate')) {
    function translate($text)
    {
        $locale = App::getLocale();

        if ($locale === 'en') {
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

        // Return existing translation if found
        if (isset($translations[$text])) {
            return $translations[$text];
        }

        // Translate via Google Translate (free endpoint)
        $response = Http::get('https://translate.googleapis.com/translate_a/single', [
            'client' => 'gtx',
            'sl'     => 'en',
            'tl'     => $locale,
            'dt'     => 't',
            'q'      => $text,
        ]);

        $translated = $response->json()[0][0][0] ?? $text;

        // Save new translation
        $translations[$text] = $translated;
        File::put($filePath, json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $translated;
    }
}
