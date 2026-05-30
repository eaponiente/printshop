<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('Telegram webhook received', $request->all());
    }
}
