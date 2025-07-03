<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync';

    protected $description = 'Scan Blade and PHP files for translate() calls, remove unused translations (except protected), and sync translations for all locales';

    public function handle()
    {
        $this->info('Scanning Blade files for translate() calls...');
        $bladeFiles = File::allFiles(resource_path('views'));

        $this->info('Scanning PHP files in /app for translate() calls...');
        $phpFiles = File::allFiles(app_path());

        // Collect all translation keys found in code with their params
        $translations = [];

        // Regex to find translate('key', [...])
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

        // Clean unused translations except protected keys
        $this->cleanUnusedTranslations(array_keys($translations));

        // Supported locales from config
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
     * Remove unused translations from all locale JSON files,
     * but keep keys defined in $dontRemove.
     *
     * @param array $usedKeys Keys currently used in code
     * @return void
     */
    protected function cleanUnusedTranslations(array $usedKeys): void
    {
        $dontRemove = [
            'crypto',
            'payments',
            'monocurrency',
            'cryptocurrency',
            'cryptocurrencies',
            'anonymous',
        ];

        $this->info('Checking for unused translations...');

        $langPath = resource_path('lang');
        $sourceFile = "{$langPath}/en-GB.json";

        if (!File::exists($sourceFile)) {
            $this->warn('Source translation file (en-GB.json) not found. Skipping cleanup.');
            return;
        }

        $sourceTranslations = json_decode(File::get($sourceFile), true);
        if (!is_array($sourceTranslations)) {
            $this->error('Failed to parse en-GB.json. Ensure valid JSON.');
            return;
        }

        // Keys present in source but NOT used in code
        $unusedKeys = array_diff(array_keys($sourceTranslations), $usedKeys);

        // Remove keys that must never be removed
        $unusedKeys = array_diff($unusedKeys, $dontRemove);

        if (empty($unusedKeys)) {
            $this->info('No unused translations found to remove.');
            return;
        }

        $this->info('Found ' . count($unusedKeys) . ' unused translation keys.');

        // Clean unused keys from all locale files
        $langFiles = File::glob("{$langPath}/*.json");

        foreach ($langFiles as $file) {
            $locale = basename($file, '.json');
            $this->info("Cleaning translations for locale: {$locale}");

            $translations = json_decode(File::get($file), true) ?? [];
            $modified = false;

            foreach ($unusedKeys as $key) {
                if (isset($translations[$key])) {
                    unset($translations[$key]);
                    $this->line("  - Removed unused key: '{$key}'");
                    $modified = true;
                }
            }

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
     * Safely parse PHP array string to assoc array for params extraction.
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
