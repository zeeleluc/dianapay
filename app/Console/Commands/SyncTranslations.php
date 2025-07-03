<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync';

    protected $description = 'Scan Blade files for translate() calls, remove unused translations, and sync translations for all languages';

    public function handle()
    {
        $this->info('Scanning Blade files for translate() calls...');
        $bladeFiles = File::allFiles(resource_path('views'));

        $this->info('Scanning PHP files in /app for translate() calls...');
        $phpFiles = File::allFiles(app_path());

        // This will store: ['text to translate' => [ 'paramKey' => 'exampleValue', ... ], ...]
        $translations = [];

        // Regex to match translate('some string', ['param' => 'value'])
        $pattern = '/translate\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[[^\]]*\]))?\s*\)/';

        foreach (array_merge($bladeFiles, $phpFiles) as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $text = $match[1];
                    $params = isset($match[2]) ? $this->parseParamsArray($match[2]) : [];
                    $translations[$text] = $params;
                }
            }
        }

        if (empty($translations)) {
            $this->info('No translate() calls found.');
            return 0;
        }

        $this->info('Found ' . count($translations) . ' unique translation strings.');

        // Clean up unused translations using en-GB as the source
        $this->cleanUnusedTranslations(array_keys($translations));

        // Get all locales from config
        $locales = config('locales.allowed', []);

        if (empty($locales)) {
            $this->error('No supported locales defined in config/locales.php');
            return 1;
        }

        foreach ($locales as $locale) {
            if ($locale === 'en-GB') continue; // Skip source language

            App::setLocale($locale);
            $this->info("Processing locale: {$locale}");

            foreach ($translations as $text => $params) {
                $translated = translate($text, $params);
                $this->line("  - '{$text}' => '{$translated}'");
            }
        }

        $this->info('Translation sync complete.');

        return 0;
    }

    /**
     * Remove unused translations from all language JSON files
     *
     * @param array $usedKeys Array of translation keys currently in use
     * @return void
     */
    protected function cleanUnusedTranslations(array $usedKeys): void
    {
        $this->info('Checking for unused translations...');

        // Path to translation files
        $langPath = resource_path('lang');

        // Get en-GB translations as the source
        $sourceFile = "{$langPath}/en-GB.json";
        if (!File::exists($sourceFile)) {
            $this->warn('Source translation file (en-GB.json) not found. Skipping unused translation cleanup.');
            return;
        }

        $sourceTranslations = json_decode(File::get($sourceFile), true);
        if (!is_array($sourceTranslations)) {
            $this->error('Failed to parse en-GB.json. Ensure it contains valid JSON.');
            return;
        }

        $unusedKeys = array_diff(array_keys($sourceTranslations), $usedKeys);

        if (empty($unusedKeys)) {
            $this->info('No unused translations found.');
            return;
        }

        $this->info('Found ' . count($unusedKeys) . ' unused translation keys.');

        // Get all language files
        $langFiles = File::glob("{$langPath}/*.json");

        foreach ($langFiles as $file) {
            $locale = basename($file, '.json');
            $this->info("Cleaning translations for locale: {$locale}");

            $translations = json_decode(File::get($file), true) ?? [];

            // Remove unused keys
            $modified = false;
            foreach ($unusedKeys as $key) {
                if (isset($translations[$key])) {
                    unset($translations[$key]);
                    $this->line("  - Removed unused key: '{$key}'");
                    $modified = true;
                }
            }

            // Save updated translations only if changes were made
            if ($modified) {
                File::put($file, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->line("  Updated translation file for {$locale}");
            } else {
                $this->line("  No changes needed for {$locale}");
            }
        }

        $this->info('Unused translations removed from all language files.');
    }

    /**
     * Parses a PHP-style array string into an associative array safely.
     *
     * @param string $arrayString
     * @return array
     */
    protected function parseParamsArray(string $arrayString): array
    {
        $cleaned = trim($arrayString);
        $jsonish = preg_replace('/=>/', ':', $cleaned);
        $jsonish = preg_replace('/\'/', '"', $jsonish);
        $decoded = json_decode($jsonish, true);
        return is_array($decoded) ? $decoded : [];
    }
}
