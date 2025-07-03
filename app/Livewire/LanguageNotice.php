<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Session;

class LanguageNotice extends Component
{
    public bool $visible = false;

    public function mount()
    {
        $locale = app()->getLocale();

        // Show notice only for non-English locales and if not dismissed yet
        if (!in_array($locale, ['en', 'en-GB']) && !Session::has('langnotice')) {
            $this->visible = true;
        }
    }

    public function dismiss()
    {
        $this->visible = false;
        Session::put('langnotice', true);
    }

    public function render()
    {
        return view('livewire.language-notice');
    }
}
