<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\RegisteredDevice;
use Inertia\Inertia;
use Illuminate\Http\Request;

class DeviceManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $pending = RegisteredDevice::with(['user:id,username,first_name,last_name,role,branch_id', 'user.branch:id,name'])
            ->pending()
            ->latest()
            ->get();

        $approved = RegisteredDevice::with(['user:id,username,first_name,last_name,role,branch_id', 'user.branch:id,name'])
            ->approved()
            ->latest()
            ->get();

        return Inertia::render('devices/index', [
            'pending' => $pending,
            'approved' => $approved,
        ]);
    }

    public function approve(Request $request, RegisteredDevice $device)
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $device->update([
            'is_approved' => true,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('message', 'Device approved.');
    }

    public function reject(Request $request, RegisteredDevice $device)
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $device->delete();

        return back()->with('message', 'Device rejected and removed.');
    }

    public function deactivate(Request $request, RegisteredDevice $device)
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $device->update(['is_active' => false]);

        return back()->with('message', 'Device deactivated.');
    }
}
