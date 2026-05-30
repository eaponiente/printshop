<?php

use App\Http\Controllers\Webhooks\TelegramController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'auth/login', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::post('/webhook/telegram', TelegramController::class)->name('webhook.telegram');

require __DIR__.'/settings.php';
