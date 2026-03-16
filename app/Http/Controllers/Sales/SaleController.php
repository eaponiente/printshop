<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Branch;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request): Response
    {
        $date = $request->input('date');
        $mode = $request->input('mode');
        $query = Transaction::query()->with('user');

        if ($date) {
            if ($mode === 'daily') {
                $query->whereDate('transaction_date', $date);
            } elseif ($mode === 'weekly') {
                // HTML5 week format is "2024-W12"
                $parts = explode('-W', $date);
                $query->whereRaw('YEAR(transaction_date) = ?', [$parts[0]])
                    ->whereRaw('WEEK(transaction_date, 1) = ?', [$parts[1]]);
            } elseif ($mode === 'monthly') {
                // HTML5 month format is "2024-05"
                $t = Carbon::parse($date);
                $query->whereMonth('transaction_date', $t->month)
                    ->whereYear('transaction_date', $t->year);
            }
        }

        return Inertia::render('sales/list', [
            'transactions' => $query->orderBy('transaction_date', 'desc')->paginate(30)->withQueryString(),
            'filters' => $request->only(['mode', 'date']),
            'branches' => Branch::get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'guest_name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
            'particular' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        return redirect()->back();
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
