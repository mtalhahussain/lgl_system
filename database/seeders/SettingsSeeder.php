<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $settings = [
            // Institute Information
            [
                'key' => 'institute_name',
                'value' => 'German Language Learning Institute',
                'type' => 'text',
                'group' => 'institute',
                'label' => 'Institute Name',
                'description' => 'Name of your institute',
                'is_public' => true
            ],
            [
                'key' => 'institute_logo',
                'value' => null,
                'type' => 'file',
                'group' => 'institute',
                'label' => 'Institute Logo',
                'description' => 'Upload institute logo',
                'is_public' => true
            ],
            [
                'key' => 'institute_address',
                'value' => '123 Main Street, City, Country',
                'type' => 'textarea',
                'group' => 'institute',
                'label' => 'Institute Address',
                'description' => 'Full address of the institute',
                'is_public' => true
            ],
            [
                'key' => 'institute_phone',
                'value' => '+1-234-567-8900',
                'type' => 'text',
                'group' => 'institute',
                'label' => 'Phone Number',
                'description' => 'Contact phone number',
                'is_public' => true
            ],
            [
                'key' => 'institute_email',
                'value' => 'info@germanlanguage.edu',
                'type' => 'email',
                'group' => 'institute',
                'label' => 'Email Address',
                'description' => 'Contact email address',
                'is_public' => true
            ],
            [
                'key' => 'institute_website',
                'value' => 'https://germanlanguage.edu',
                'type' => 'url',
                'group' => 'institute',
                'label' => 'Website URL',
                'description' => 'Institute website URL',
                'is_public' => true
            ],
            
            // Currency Settings
            [
                'key' => 'currency_code',
                'value' => 'PKR',
                'type' => 'select',
                'group' => 'currency',
                'label' => 'Currency Code',
                'description' => 'Default currency for the system',
                'options' => ['PKR' => 'Pakistani Rupee (PKR)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)'],
                'is_public' => true
            ],
            [
                'key' => 'currency_symbol',
                'value' => 'Rs.',
                'type' => 'text',
                'group' => 'currency',
                'label' => 'Currency Symbol',
                'description' => 'Currency symbol to display',
                'is_public' => true
            ],
            [
                'key' => 'currency_position',
                'value' => 'before',
                'type' => 'select',
                'group' => 'currency',
                'label' => 'Currency Position',
                'description' => 'Position of currency symbol',
                'options' => ['before' => 'Before Amount (Rs. 1000)', 'after' => 'After Amount (1000 Rs.)'],
                'is_public' => true
            ],
            [
                'key' => 'currency_decimals',
                'value' => '2',
                'type' => 'number',
                'group' => 'currency',
                'label' => 'Decimal Places',
                'description' => 'Number of decimal places for currency',
                'is_public' => true
            ],
            
            // Fee Settings
            [
                'key' => 'default_installments',
                'value' => '3',
                'type' => 'number',
                'group' => 'fees',
                'label' => 'Default Installments',
                'description' => 'Default number of fee installments',
                'is_public' => false
            ],
            [
                'key' => 'late_fee_percentage',
                'value' => '5',
                'type' => 'number',
                'group' => 'fees',
                'label' => 'Late Fee Percentage',
                'description' => 'Percentage for late fee charges',
                'is_public' => false
            ],
            [
                'key' => 'early_payment_discount',
                'value' => '2',
                'type' => 'number',
                'group' => 'fees',
                'label' => 'Early Payment Discount (%)',
                'description' => 'Discount percentage for early payments',
                'is_public' => false
            ],
            [
                'key' => 'registration_fee',
                'value' => '1000',
                'type' => 'number',
                'group' => 'fees',
                'label' => 'Registration Fee',
                'description' => 'One-time registration fee for new students',
                'is_public' => false
            ],
            
            // System Settings
            [
                'key' => 'timezone',
                'value' => 'Asia/Karachi',
                'type' => 'select',
                'group' => 'system',
                'label' => 'Timezone',
                'description' => 'Default timezone for the system',
                'options' => [
                    'Asia/Karachi' => 'Asia/Karachi (PKT)',
                    'America/New_York' => 'America/New_York (EST)',
                    'Europe/London' => 'Europe/London (GMT)',
                    'Europe/Berlin' => 'Europe/Berlin (CET)'
                ],
                'is_public' => false
            ],
            [
                'key' => 'date_format',
                'value' => 'd/m/Y',
                'type' => 'select',
                'group' => 'system',
                'label' => 'Date Format',
                'description' => 'Default date format for display',
                'options' => [
                    'd/m/Y' => 'DD/MM/YYYY',
                    'm/d/Y' => 'MM/DD/YYYY',
                    'Y-m-d' => 'YYYY-MM-DD',
                    'j F, Y' => 'Day Month, Year'
                ],
                'is_public' => true
            ],
            [
                'key' => 'backup_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'system',
                'label' => 'Enable Automatic Backups',
                'description' => 'Enable automatic database backups',
                'is_public' => false
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'system',
                'label' => 'Maintenance Mode',
                'description' => 'Put system in maintenance mode',
                'is_public' => false
            ],
            
            // Appearance Settings
            [
                'key' => 'primary_color',
                'value' => '#007bff',
                'type' => 'color',
                'group' => 'appearance',
                'label' => 'Primary Color',
                'description' => 'Primary theme color',
                'is_public' => true
            ],
            [
                'key' => 'secondary_color',
                'value' => '#6c757d',
                'type' => 'color',
                'group' => 'appearance',
                'label' => 'Secondary Color',
                'description' => 'Secondary theme color',
                'is_public' => true
            ],
            [
                'key' => 'items_per_page',
                'value' => '10',
                'type' => 'number',
                'group' => 'appearance',
                'label' => 'Items Per Page',
                'description' => 'Number of items to show per page in listings',
                'is_public' => false
            ]
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
