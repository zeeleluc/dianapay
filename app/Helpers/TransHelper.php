<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if (!function_exists('translate')) {
    function translate(string $text, array $replace = []): string
    {
        return Translator::translate($text, $replace, true);
    }
}

class Translator
{
    protected static array $highlightWords = [
        'crypto',
        'payments',
        'monocurrency',
        'cryptocurrency',
        'cryptocurrencies',
        'anonymous',
    ];

    protected static array $customWordOverrides = [
        'crypto' => [
            'en'    => 'crypto',
            'en-GB' => 'crypto',
            'es'    => 'cripto',
            'zh-CN' => '加密货币',     // jiāmì huòbì (crypto currency)
            'zh-TW' => '加密貨幣',
            'ar'    => 'كريبتو',
            'hi'    => 'क्रिप्टो',
            'fr'    => 'crypto',
            'de'    => 'Krypto',
            'ru'    => 'крипто',
            'pt'    => 'cripto',
            'ja'    => '暗号資産',     // angō shisan (crypto asset)
            'ko'    => '크립토',
            'it'    => 'cripto',
            'tr'    => 'kripto',
            'nl'    => 'crypto',
            'sv'    => 'krypto',
            'pl'    => 'krypto',
            'vi'    => 'tiền mã hóa', // or just 'crypto'
            'id'    => 'kripto',
            'th'    => 'คริปโต',
            'ms'    => 'kripto',
            'fa'    => 'کریپتو',
            'pap'   => 'kripto',
        ],

        'cryptocurrency' => [
            'en'    => 'cryptocurrency',
            'en-GB' => 'cryptocurrency',
            'es'    => 'criptomoneda',
            'zh-CN' => '加密货币',
            'zh-TW' => '加密貨幣',
            'ar'    => 'عملة مشفرة',
            'hi'    => 'क्रिप्टोकरेंसी',
            'fr'    => 'cryptomonnaie',
            'de'    => 'Kryptowährung',
            'ru'    => 'криптовалюта',
            'pt'    => 'criptomoeda',
            'ja'    => '暗号通貨',
            'ko'    => '암호화폐',
            'it'    => 'criptovaluta',
            'tr'    => 'kripto para',
            'nl'    => 'cryptomunt',
            'sv'    => 'kryptovaluta',
            'pl'    => 'kryptowaluta',
            'vi'    => 'tiền điện tử',
            'id'    => 'mata uang kripto',
            'th'    => 'สกุลเงินดิจิทัล',
            'ms'    => 'mata wang kripto',
            'fa'    => 'رمزارز',
            'pap'   => 'moneda kripto',
        ],
    ];

    public static function translate(string $text, array $replace = [], bool $highlight = true): string
    {
        $locale = App::getLocale();

        // Handle exceptions
        $exceptions = [
            'passwords.user'      => "We can't find a user with that email address.",
            'validation.required' => 'The :attribute field is required.',
            'validation.string'   => 'The :attribute must be a string.',
            'auth.password'       => 'The provided password is incorrect.',
        ];

        if (isset($exceptions[$text])) {
            $text = $exceptions[$text];
        }

        // Replace variables like :attribute
        $text = self::applyReplacements($text, $replace);

        if ($locale === 'en') {
            return $highlight ? self::highlight($text) : $text;
        }

        // Translation caching file
        $langFile = resource_path("lang/{$locale}.json");
        $translations = File::exists($langFile)
            ? json_decode(File::get($langFile), true) ?? []
            : [];

        // Return cached translation if available
        if (isset($translations[$text])) {
            return $highlight ? self::highlight($translations[$text]) : $translations[$text];
        }

        // Skip translating if placeholders weren't replaced
        if (preg_match('/:[a-zA-Z0-9_]+/', $text)) {
            Log::warning("Unreplaced placeholders in string: {$text}");
            return $highlight ? self::highlight($text) : $text;
        }

        // Apply custom word replacements
        $textForTranslation = self::applyCustomOverrides($text, $locale);

        // Translate using Google API
        $translated = self::fetchGoogleTranslation($textForTranslation, $locale) ?? $text;

        // Vervang "uw" door "je" alleen voor Nederlands
        if ($locale === 'nl') {
            $translated = preg_replace_callback('/\b(uw)\b/i', function ($matches) {
                return self::matchCase('je', $matches[1]);
            }, $translated);
        }

        // Save to translation cache
        $translations[$text] = $translated;
        File::put($langFile, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $highlight ? self::highlight($translated) : $translated;
    }

    protected static function applyReplacements(string $text, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':' . $key, $value, $text);
        }
        return $text;
    }

    protected static function applyCustomOverrides(string $text, string $locale): string
    {
        foreach (self::$customWordOverrides as $word => $overrides) {
            if (!isset($overrides[$locale])) continue;

            $translated = $overrides[$locale];

            $text = preg_replace_callback(
                '/\b(' . preg_quote($word, '/') . ')\b/i',
                function ($matches) use ($translated) {
                    return self::matchCase($translated, $matches[1]);
                },
                $text
            );
        }
        return $text;
    }

    protected static function fetchGoogleTranslation(string $text, string $locale): ?string
    {
        try {
            $response = Http::get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl'     => 'en',
                'tl'     => $locale,
                'dt'     => 't',
                'q'      => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data[0][0][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error("Google Translate API failed: " . $e->getMessage());
        }

        return null;
    }

    protected static function highlight(string $text): string
    {
        foreach (self::getHighlightWords() as $word) {
            $pattern = '/\b(' . preg_quote($word, '/') . ')\b/i';

            $text = preg_replace_callback(
                $pattern,
                function ($matches) {
                    return '<span style="color: #FACC14">' . $matches[1] . '</span>';
                },
                $text
            );
        }
        return $text;
    }

    protected static function getHighlightWords(): array
    {
        static $translated = [];

        $locale = App::getLocale();
        if (!isset($translated[$locale])) {
            $translated[$locale] = [];

            if ($locale === 'en') {
                $translated[$locale] = self::$highlightWords;
            } else {
                foreach (self::$highlightWords as $word) {
                    $translatedWord = self::translate($word, [], false);
                    $translated[$locale][] = $translatedWord;
                }
            }
        }

        return $translated[$locale];
    }

    protected static function matchCase(string $replacement, string $original): string
    {
        // UPPER CASE
        if (mb_strtoupper($original) === $original) {
            return mb_strtoupper($replacement);
        }

        // Title Case
        if (mb_strtoupper(mb_substr($original, 0, 1)) . mb_strtolower(mb_substr($original, 1)) === $original) {
            return mb_strtoupper(mb_substr($replacement, 0, 1)) . mb_substr($replacement, 1);
        }

        // Lower case or default
        return mb_strtolower($replacement);
    }

}
