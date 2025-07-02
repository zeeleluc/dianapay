<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

class LanguageSwitcher extends Component
{
    public bool $open = false;
    public string $locale;
    public array $allowedLocales;
    public array $flags;

    public $localeLabels = [
        'en'    => 'USA',
        'en-GB' => 'EN',
        'es'    => 'ES',
        'zh-CN' => 'CN',
        'zh-TW' => 'TW',
        'ar'    => 'AR',
        'hi'    => 'HI',
        'fr'    => 'FR',
        'de'    => 'DE',
        'ru'    => 'RU',
        'pt'    => 'PT',
        'ja'    => 'JA',
        'ko'    => 'KO',
        'it'    => 'IT',
        'tr'    => 'TR',
        'nl'    => 'NL',
        'sv'    => 'SV',
        'pl'    => 'PL',
        'vi'    => 'VI',
        'id'    => 'ID',
        'th'    => 'TH',
        'ms'    => 'MS',
        'fa'    => 'FA',
        'he'    => 'HE',
        'pap'   => 'PAP',
    ];

    public function mount()
    {
        $this->allowedLocales = Config::get('locales.allowed', ['en']);
        $this->flags = Config::get('language_flags', []);
        $this->locale = Session::get('locale', Config::get('app.locale', 'en'));
        \Illuminate\Support\Facades\App::setLocale($this->locale);
        Log::info('LanguageSwitcher mounted', ['locale' => $this->locale]);
    }

    public function switchLocale(string $locale)
    {
        Log::info('LanguageSwitcher switchLocale called', [
            'locale' => $locale,
            'currentRouteName' => Route::currentRouteName(),
            'currentParams' => Route::current()->parameters(),
        ]);

        if (in_array($locale, $this->allowedLocales)) {
            Session::put('locale', $locale);
            \Illuminate\Support\Facades\App::setLocale($locale);
            $this->locale = $locale;
            $this->open = false;

            // Get the last visited route from the session
            $lastRoute = Session::get('last_route', [
                'name' => 'home',
                'params' => ['locale' => $locale],
            ]);

            // Build the redirect URL
            $redirectUrl = route($lastRoute['name'], array_merge($lastRoute['params'], ['locale' => $locale]));
            Log::info('LanguageSwitcher redirecting', ['redirectUrl' => $redirectUrl]);

            return Redirect::to($redirectUrl, 301);
        }
    }

    public function render()
    {
        // Store the current route in the session if it's not a Livewire or lang switch route
        $currentRouteName = Route::currentRouteName();
        if ($currentRouteName && !in_array($currentRouteName, ['lang.switch', 'locale.redirect']) && !request()->is('livewire/*')) {
            Session::put('last_route', [
                'name' => $currentRouteName,
                'params' => Route::current()->parameters(),
            ]);
        }

        Log::info('LanguageSwitcher rendering', ['locale' => $this->locale]);
        return view('livewire.language-switcher');
    }
}
