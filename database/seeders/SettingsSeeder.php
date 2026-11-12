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
                'value' => 'Land of German Language',
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
                'value' => 'FL 4/16, 2nd Floor, Block 5, Rashid Minhas Road, Gulshan-e-Iqbal, Karachi, Pakistan',
                'type' => 'textarea',
                'group' => 'institute',
                'label' => 'Institute Address',
                'description' => 'Full address of the institute',
                'is_public' => true
            ],
            [
                'key' => 'institute_phone',
                'value' => '+923240229441',
                'type' => 'text',
                'group' => 'institute',
                'label' => 'Phone Number',
                'description' => 'Contact phone number',
                'is_public' => true
            ],
            [
                'key' => 'institute_email',
                'value' => 'landofgermanlanguage@gmail.com',
                'type' => 'email',
                'group' => 'institute',
                'label' => 'Email Address',
                'description' => 'Contact email address',
                'is_public' => true
            ],
            [
                'key' => 'institute_website',
                'value' => 'https://landofgermanlanguage.com',
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
