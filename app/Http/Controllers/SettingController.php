<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Setting::class);

        $settingGroups = Setting::whereIn('group', ['institute', 'currency'])->get()->groupBy('group');
        $machines = \App\Models\BiometricDevice::where('is_active', true)->get(['id', 'last_seen_at']);
        $integrations = [
            'whatsapp' => ['enabled' => app(\App\Services\FeeReminderService::class)->settings()['enabled'],
                'configured' => app(\App\Services\WhatsAppService::class)->configured()],
            'biometric' => ['machines' => $machines->count(), 'last_seen' => $machines->max('last_seen_at')],
        ];

        return view('admin.settings.index', compact('settingGroups', 'integrations'));
    }

    public function update(Request $request)
    {
        $this->authorize('update', Setting::class);
        $data = $request->validate([
            'institute_name' => 'sometimes|required|string|max:255', 'institute_address' => 'nullable|string|max:1000',
            'institute_phone' => 'nullable|string|max:30|regex:/^\+?[0-9]+$/D', 'institute_email' => 'nullable|email|max:255',
            'institute_website' => 'nullable|url:http,https|max:255',
            'institute_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'currency_code' => 'sometimes|in:PKR,USD,EUR,GBP', 'currency_symbol' => 'sometimes|string|max:10',
            'currency_position' => 'sometimes|in:before,after', 'currency_decimals' => 'sometimes|integer|min:0|max:2',
        ]);
        if ($request->hasFile('institute_logo')) {
            $data['institute_logo'] = $request->file('institute_logo')->store('settings', 'public');
        } else {
            unset($data['institute_logo']);
        }
        if (isset($data['currency_code']) && $data['currency_code'] !== Setting::get('currency_code', 'PKR')
            && \Illuminate\Support\Facades\Schema::hasTable('payments') && \App\Models\Payment::exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['currency_code' => 'Currency cannot change after payments have been recorded.']);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                Setting::where('key', $key)->update(['value' => $value]);
            }
        });

        return redirect()->route('settings.index')->with('success', 'Settings updated successfully.');
    }

    // Helper method to get formatted currency
    public static function getCurrency($amount)
    {
        $symbol = Setting::get('currency_symbol', '$');
        $position = Setting::get('currency_position', 'before');
        $decimals = Setting::get('currency_decimals', 2);

        $formatted = number_format($amount, $decimals);

        return $position === 'before' ? $symbol.' '.$formatted : $formatted.' '.$symbol;
    }

    // Helper method to get institute name
    public static function getInstituteName()
    {
        return Setting::get('institute_name', 'Language Institute');
    }

    // Helper method to get currency symbol
    public static function getCurrencySymbol()
    {
        return Setting::get('currency_symbol', '$');
    }
}
