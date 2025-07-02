<?php

use Illuminate\Support\Facades\Session;

if (!function_exists('get_locale')) {
    function get_locale()
    {
        return Session::get('locale', config('app.locale'));
    }
}
