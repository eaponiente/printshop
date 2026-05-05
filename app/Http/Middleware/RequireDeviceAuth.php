<?php

namespace App\Http\Middleware;

use App\Models\RegisteredDevice;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequireDeviceAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('features.device_auth', true)) {
            return $next($request);
        }

        if (! $request->user()) {
            return $next($request);
        }

        if ($request->user()->isSuperAdmin()) {
            return $next($request);
        }

        $excludedPaths = [
            '/',
            'logout',
            'device-auth/*',
            'devices*',
            'two-factor-challenge',
            'two-factor-challenge/*',
            'confirm-password',
            'confirm-password/*',
            'verify-email',
            'verify-email/*',
        ];

        foreach ($excludedPaths as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        if ($request->session()->get('device_verified') === $request->user()->id) {
            return $next($request);
        }

        $token = $request->cookie('device_token');

        if ($token) {
            $device = RegisteredDevice::where('device_token', $token)
                ->where('user_id', $request->user()->id)
                ->approved()
                ->first();

            if ($device) {
                $device->touch('last_used_at');
                $request->session()->put('device_verified', $request->user()->id);

                return $next($request);
            }
        }

        $devices = RegisteredDevice::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->get();

        $hasApproved = $devices->contains(fn ($d) => $d->is_approved);
        $hasPending = $devices->contains(fn ($d) => ! $d->is_approved);

        return Inertia::render('auth/device-auth', [
            'userId' => $request->user()->id,
            'hasApproved' => $hasApproved,
            'hasPending' => $hasPending,
        ]);
    }
}
