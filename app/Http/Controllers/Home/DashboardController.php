<?php

namespace App\Http\Controllers\Home;

use App\Enums\Sales\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('dashboard/dashboard');
    }

    private function calculateGrowth($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
