<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollSetting;
use Cache;

class PayrollSettingService
{
    private const CACHE_KEY = 'payroll_settings.all';

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        PayrollSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->loadFromDb());
    }

    private function loadFromDb(): array
    {
        return PayrollSetting::all()->pluck('value', 'key')->toArray();
    }

    public function loadAllModels(): array
    {
        return PayrollSetting::all()->all();
    }
}
