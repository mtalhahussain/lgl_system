<?php

// Meta WhatsApp Cloud API. Secrets live only in .env; reminder options are Admin settings (group "whatsapp").
return [
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'version' => env('WHATSAPP_VERSION', 'v20.0'),
    'base_url' => 'https://graph.facebook.com',
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    // Numbers starting with 0 (local format) get this country code.
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '92'),
];
