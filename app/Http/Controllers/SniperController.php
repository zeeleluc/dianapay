<?php

namespace App\Http\Controllers;

use App\Models\SolanaCall;
use Illuminate\Http\Request;

class SniperController extends Controller
{
    public function index()
    {
        // Get all SolanaCall records and eager load related orders
        $solanaCalls = SolanaCall::with('orders')->get();

        return view('sniper.index', compact('solanaCalls'));
    }
}
