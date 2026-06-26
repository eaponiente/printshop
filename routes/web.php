<?php

use Database\Seeders\HolidaySeeder;
use Database\Seeders\SssBracketSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'auth/login', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/setup/seed', function () {
    if (request('token') !== config('app.key')) {
        abort(403);
    }

    app(HolidaySeeder::class)->run();
    app(SssBracketSeeder::class)->run();

    return response()->json(['status' => 'ok', 'message' => 'Holidays and SSS brackets seeded.']);
});

require __DIR__.'/settings.php';
