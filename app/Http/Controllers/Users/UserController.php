<?php

namespace App\Http\Controllers\Users;

use App\Enums\Sales\TransactionStatus;
use App\Enums\Sublimations\SublimationStatus;
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
        $users = User::query()->with('branch')
            ->whereIn('role', ['staff', 'admin'])
            ->when(auth()->user()->role !== 'superadmin', function ($q) {
                $q->where('branch_id', auth()->user()->branch_id);
            });

        return Inertia::render('users/list', [
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'users' => $users->paginate()->withQueryString(),
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
            // throw exception if user transactions (where status is not paid) or sublimations (status is not completed) > 0
            if ($user->transactions()->where('status', '!=', TransactionStatus::PAID)->count() > 0 ||
                $user->sublimations()->where('status', '!=', SublimationStatus::COMPLETED->value)->count() > 0) {
                throw new \Exception('User has active transactions or sublimations');
            }

            $user->delete();

            return redirect()->back()->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete user: '.$e->getMessage());

            return redirect()->back()->withErrors([
                'message' => $e->getMessage(),
                'error' => 'An error occurred while deleting the user.'
            ]);
        }
    }
}
