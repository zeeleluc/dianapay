<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Request;

class Navbar extends Component
{
    public bool $large = false;

    public function mount()
    {
        $this->large = Request::is('/');
    }

    public function render()
    {
        return view('livewire.navbar');
    }
}
