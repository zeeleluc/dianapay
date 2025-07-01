<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

class LanguageSwitcher extends Component
{
    public bool $open = false;
    public string $locale;
    public array $allowedLocales;
    public array $flags;

    public $localeLabels = [
        'en' => 'USA',
        'en-GB' => 'EN',
        'es' => 'ES',
        'zh-CN' => 'CN',
        'zh-TW' => 'TW',
        'ar' => 'AR',
        'hi' => 'HI',
        'fr' => 'FR',
        'de' => 'DE',
        'ru' => 'RU',
        'pt' => 'PT',
        'ja' => 'JA',
        'ko' => 'KO',
        'it' => 'IT',
        'tr' => 'TR',
        'nl' => 'NL',
        'sv' => 'SV',
        'pl' => 'PL',
        'vi' => 'VI',
        'id' => 'ID',
        'th' => 'TH',
        'ms' => 'MS',
        'fa' => 'FA',
        'he' => 'HE',
    ];

    public function mount()
    {
        $this->allowedLocales = Config::get('locales.allowed', ['en']);
        $this->flags = Config::get('language_flags', []);
        $this->locale = Session::get('locale', Config::get('app.locale', 'en'));
        App::setLocale($this->locale);
    }

    public function switchLocale(string $locale): void
    {
        if (in_array($locale, $this->allowedLocales)) {
            Session::put('locale', $locale);
            App::setLocale($locale);
            $this->locale = $locale;
            $this->open = false;
            $this->redirect(request()->header('referer') ?? '/');
        }
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
