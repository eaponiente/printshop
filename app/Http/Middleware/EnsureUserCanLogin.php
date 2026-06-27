<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserCanLogin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $user->canLogin()) {
            Auth::guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home')->withErrors([
                'username' => 'Your account has been deactivated. Please contact your administrator.',
            ]);
        }

        return $next($request);
    }
}
