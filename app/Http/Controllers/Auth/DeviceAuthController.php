<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RegisteredDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeviceAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $token = Str::random(64);

        $autoApprove = $user->isSuperAdmin();

        RegisteredDevice::create([
            'user_id' => $user->id,
            'device_token' => $token,
            'device_name' => $data['device_name'],
            'branch_id' => $user->branch_id,
            'registered_by' => $user->id,
            'last_used_at' => now(),
            'is_active' => true,
            'is_approved' => $autoApprove,
            'approved_by' => $autoApprove ? $user->id : null,
            'approved_at' => $autoApprove ? now() : null,
        ]);

        $cookie = cookie('device_token', $token, 60 * 24 * 365); // 1 year

        if ($autoApprove) {
            $request->session()->put('device_verified', $user->id);

            return response()->json([
                'verified' => true,
                'redirect' => route('dashboard'),
            ])->withCookie($cookie);
        }

        return response()->json([
            'pending' => true,
            'message' => 'Device registered. Awaiting superadmin approval.',
        ])->withCookie($cookie);
    }
}
