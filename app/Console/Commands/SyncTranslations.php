<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync';

    protected $description = 'Scan Blade files for translate() calls and sync translations for all languages';

    public function handle()
    {
        $this->info('Scanning Blade files for translate() calls...');

        $bladeFiles = File::allFiles(resource_path('views'));

        // This will store: ['text to translate' => [ 'paramKey' => 'exampleValue', ... ], ...]
        $translations = [];

        // Regex to match translate('some string', ['param' => 'value'])
        $pattern = '/translate\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[[^\]]*\]))?\s*\)/';

        foreach ($bladeFiles as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $text = $match[1];

                    // Params can be empty
                    $params = [];
                    if (isset($match[2])) {
                        // Evaluate array string safely
                        $params = $this->parseParamsArray($match[2]);
                    }

                    $translations[$text] = $params;
                }
            }
        }

        if (empty($translations)) {
            $this->info('No translate() calls found.');
            return 0;
        }

        $this->info('Found ' . count($translations) . ' unique translation strings.');

        // Get all locales from config except 'en'
        $locales = config('locales.allowed', []);

        if (empty($locales)) {
            $this->error('No supported locales defined in config/locales.php');
            return 1;
        }

        foreach ($locales as $locale) {
            if ($locale === 'en') continue; // Skip English base

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
     * Parses a PHP-style array string into an associative array safely.
     *
     * @param string $arrayString
     * @return array
     */
    protected function parseParamsArray(string $arrayString): array
    {
        // Remove newlines/spaces
        $cleaned = trim($arrayString);

        // Replace => with : for JSON compatibility
        $jsonish = preg_replace('/=>/', ':', $cleaned);

        // Convert single quotes to double quotes
        $jsonish = preg_replace('/\'/', '"', $jsonish);

        // Attempt to decode JSON
        $decoded = json_decode($jsonish, true);

        return is_array($decoded) ? $decoded : [];
    }
}
