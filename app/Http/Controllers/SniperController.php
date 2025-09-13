<?php

namespace App\Http\Controllers;

use App\Models\SolanaCall;
use Illuminate\Http\Request;

class SniperController extends Controller
{
    public function index()
    {
        // Get all SolanaCall records, newest first, eager load related orders
        $solanaCalls = SolanaCall::with('orders')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('sniper.index', compact('solanaCalls'));
    }
}
