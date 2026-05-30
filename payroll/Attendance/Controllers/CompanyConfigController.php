<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\CompanyConfig;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyConfigController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $configs = CompanyConfig::all()->keyBy('key');

        return Inertia::render('payroll/settings/company-config', [
            'configs' => $configs,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'configs' => ['required', 'array'],
            'configs.*.key' => ['required', 'string'],
            'configs.*.value' => ['required', 'string'],
            'configs.*.label' => ['required', 'string'],
        ]);

        foreach ($validated['configs'] as $config) {
            CompanyConfig::updateOrCreate(
                ['key' => $config['key']],
                ['value' => $config['value'], 'label' => $config['label']],
            );
        }

        return back()->with('success', 'Configuration saved.');
    }
}
