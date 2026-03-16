<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        return Inertia::render('users/list', [
            'branches' => Branch::get(),
            'users' => User::with('branch')->whereIn('role', ['staff', 'admin'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')
                    ->whereNull('deleted_at'),
            ],
            'branch_id' => 'required|exists:branches,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'role' => ['required', Rule::in(['staff', 'admin'])],
            // You'll also need a password for new users
            'password' => 'required|string|min:6|confirmed',
        ]);

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['username'],
            'role' => $validated['role'],
            'password' => bcrypt($validated['password']),
        ]);

        return redirect()->back();
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', auth()->user());

        $rules = [
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')
                    ->whereNull('deleted_at')
                    ->ignore($user->id),
            ],
            'branch_id' => 'required|exists:branches,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'role' => ['required', Rule::in(['staff', 'admin'])],
        ];

        if (filled($request->input('password'))) {
            $rules['password'] = 'required|string|min:6|confirmed';
        }

        $validated = $request->validate($rules);

        $user->update($validated);

        return redirect()->back();
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', auth()->user());

        $user->delete();
    }
}
