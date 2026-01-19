<?php

if (!function_exists('setting')) {
    /**
     * Get setting value
     */
    function setting($key, $default = null)
    {
        return \App\Models\Setting::get($key, $default);
    }
}

if (!function_exists('currency_format')) {
    /**
     * Format amount with currency settings
     */
    function currency_format($amount)
    {
        $symbol = setting('currency_symbol', 'Rs.');
        $position = setting('currency_position', 'before');
        $decimals = setting('currency_decimals', 2);
        
        $formatted = number_format($amount, $decimals);
        
        return $position === 'before' ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
    }
}

if (!function_exists('institute_name')) {
    /**
     * Get institute name
     */
    function institute_name()
    {
        return setting('institute_name', 'Language Institute');
    }
}

if (!function_exists('currency_symbol')) {
    /**
     * Get currency symbol
     */
    function currency_symbol()
    {
        return setting('currency_symbol', 'Rs.');
    }
}