<?php

namespace App\Http\Controllers;

use App\Helpers\SlackNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'level'   => 'required|string|in:info,success,error,warning',
            'message' => 'required|string',
        ]);

        // Write to Laravel log
        Log::{$validated['level']}($validated['message']);

        // Push to Slack
        SlackNotifier::send($validated['level'], $validated['message']);

        return response()->json(['status' => 'ok']);
    }
}
