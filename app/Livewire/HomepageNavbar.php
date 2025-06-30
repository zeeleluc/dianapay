<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Request;

class HomepageNavbar extends Component
{
    public function mount()
    {
        $this->large = Request::is('/');
    }

    public function render()
    {
        return view('livewire.homepage-navbar');
    }
}
