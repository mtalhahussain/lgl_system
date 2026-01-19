<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Setting::class);
        
        $settingGroups = Setting::all()->groupBy('group');
        
        return view('admin.settings.index', compact('settingGroups'));
    }

    public function update(Request $request)
    {
        $this->authorize('update', Setting::class);
        
        $settings = $request->except('_token', '_method');
        
        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            
            if ($setting) {
                // Handle file uploads
                if ($setting->type === 'file' && $request->hasFile($key)) {
                    // Delete old file if exists
                    if ($setting->value && Storage::disk('public')->exists($setting->value)) {
                        Storage::disk('public')->delete($setting->value);
                    }
                    
                    // Store new file
                    $path = $request->file($key)->store('settings', 'public');
                    $value = $path;
                }
                
                // Handle boolean values
                if ($setting->type === 'boolean') {
                    $value = $request->has($key) ? '1' : '0';
                }
                
                $setting->update(['value' => $value]);
            }
        }
        
        return redirect()->route('settings.index')
            ->with('success', 'Settings updated successfully!');
    }

    // Helper method to get formatted currency
    public static function getCurrency($amount)
    {
        $symbol = Setting::get('currency_symbol', '$');
        $position = Setting::get('currency_position', 'before');
        $decimals = Setting::get('currency_decimals', 2);
        
        $formatted = number_format($amount, $decimals);
        
        return $position === 'before' ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
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
