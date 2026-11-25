<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const LOGOS = [
        'institute_logo' => 'Login logo',
        'sidebar_logo' => 'Sidebar logo',
        'document_logo' => 'Payslip / Receipt logo',
    ];

    public function index()
    {
        $this->authorize('viewAny', Setting::class);

        $settingGroups = Setting::whereIn('group', ['institute', 'currency'])->get()->groupBy('group');
        $machines = \App\Models\BiometricDevice::where('is_active', true)->get(['id', 'last_seen_at']);
        $whatsapp = app(\App\Services\FeeReminderService::class)->settings();
        $integrations = [
            'whatsapp' => ['enabled' => $whatsapp['enabled'], 'number' => $whatsapp['number'],
                'configured' => app(\App\Services\WhatsAppService::class)->configured()],
            'biometric' => ['machines' => $machines->count(), 'last_seen' => $machines->max('last_seen_at')],
        ];

        return view('admin.settings.index', compact('settingGroups', 'integrations', 'whatsapp'));
    }

    public function update(Request $request)
    {
        $this->authorize('update', Setting::class);
        $data = $request->validate([
            'institute_name' => 'sometimes|required|string|max:255', 'institute_address' => 'nullable|string|max:1000',
            'institute_phone' => 'nullable|string|max:30|regex:/^\+?[0-9]+$/D', 'institute_email' => 'nullable|email|max:255',
            'institute_website' => 'nullable|url:http,https|max:255',
            'institute_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sidebar_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'document_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'currency_code' => 'sometimes|in:PKR,USD,EUR,GBP', 'currency_symbol' => 'sometimes|string|max:10',
            'currency_position' => 'sometimes|in:before,after', 'currency_decimals' => 'sometimes|integer|min:0|max:2',
            'whatsapp_section' => 'sometimes|accepted', 'whatsapp_enabled' => 'sometimes|boolean',
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 ()-]*$/D'],
        ], ['whatsapp_number.regex' => 'Digits only, with an optional leading +.']);
        // The WhatsApp card posts a marker so an unticked switch means "off"; other forms leave reminders untouched.
        $whatsapp = null;
        if ($request->has('whatsapp_section')) {
            $number = null;
            if (filled($data['whatsapp_number'] ?? null)) {
                $number = app(\App\Services\WhatsAppService::class)->normalizePhone($data['whatsapp_number']);
                if ($number === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['whatsapp_number' => 'Enter a valid WhatsApp number, e.g. 03001234567 or +923001234567.']);
                }
            }
            if ($request->boolean('whatsapp_enabled') && $number === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['whatsapp_number' => 'Add the institute WhatsApp number before switching reminders on.']);
            }
            $whatsapp = ['whatsapp_reminders_enabled' => $request->boolean('whatsapp_enabled') ? '1' : '0', 'whatsapp_number' => $number];
        }
        unset($data['whatsapp_section'], $data['whatsapp_enabled'], $data['whatsapp_number']);
        if (isset($data['currency_code']) && $data['currency_code'] !== Setting::get('currency_code', 'PKR')
            && \Illuminate\Support\Facades\Schema::hasTable('payments') && \App\Models\Payment::exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['currency_code' => 'Currency cannot change after payments have been recorded.']);
        }
        foreach (array_keys(self::LOGOS) as $key) {
            unset($data[$key]);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request, $whatsapp) {
            foreach ($data as $key => $value) {
                Setting::where('key', $key)->update(['value' => $value]);
            }
            if ($whatsapp !== null) {
                $reminders = app(\App\Services\FeeReminderService::class);
                $before = $reminders->settings();
                Setting::updateOrCreate(['key' => 'whatsapp_reminders_enabled'], ['value' => $whatsapp['whatsapp_reminders_enabled'],
                    'type' => 'boolean', 'group' => 'whatsapp', 'label' => 'Send automatic WhatsApp fee reminders', 'is_public' => false]);
                Setting::updateOrCreate(['key' => 'whatsapp_number'], ['value' => $whatsapp['whatsapp_number'],
                    'type' => 'text', 'group' => 'whatsapp', 'label' => 'Institute WhatsApp number', 'is_public' => false]);
                $after = $reminders->settings();
                if ($before !== $after) {
                    \App\Models\AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'whatsapp.settings_updated', 'subject_type' => 'setting',
                        'subject_id' => Setting::where('key', 'whatsapp_reminders_enabled')->value('id') ?? 0,
                        'changes' => ['before' => $before, 'after' => $after], 'created_at' => now()]);
                }
            }
            foreach (self::LOGOS as $key => $label) {
                if ($request->hasFile($key)) {
                    $path = $request->file($key)->store('settings', 'public');
                    Setting::updateOrCreate(['key' => $key], [
                        'value' => $path, 'type' => 'file', 'group' => 'institute',
                        'label' => $label, 'is_public' => true,
                    ]);
                }
            }
        });

        return redirect()->route('settings.index', $request->query('show') === 'true' ? ['show' => 'true'] : [])
            ->with('success', 'Settings updated successfully.');
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
