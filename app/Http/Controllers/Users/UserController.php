<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        return Inertia::render('users/list', [
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'users' => User::with('branch')
                ->when(auth()->user()->role !== 'superadmin', function ($q) use ($request) {
                    $q->where('branch_id', auth()->user()->branch_id);
                })
                ->whereIn('role', ['staff', 'admin'])->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'username' => $request->username,
                'role' => $request->role,
                'password' => bcrypt($request->password),
                'branch_id' => $request->branch_id,
            ]);

            return redirect()->back()->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create user: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while creating the user.']);
        }
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $validated = $request->validated();

            if (isset($validated['password'])) {
                $validated['password'] = bcrypt($validated['password']);
            }

            $user->update($validated);

            return redirect()->back()->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update user: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while updating the user.']);
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        try {
            $user->delete();

            return redirect()->back()->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete user: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while deleting the user.']);
        }
    }
}
