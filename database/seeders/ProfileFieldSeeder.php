<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfileField;

class ProfileFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            [
                'key' => 'full_name',
                'label' => 'نام و نام خانوادگی',
                'type' => 'text',
                'required' => true,
                'enabled' => true,
            ],
            [
                'key' => 'mobile',
                'label' => 'شماره موبایل',
                'type' => 'text',
                'required' => true,
                'enabled' => true,
            ],
            [
                'key' => 'postal_code',
                'label' => 'کد پستی',
                'type' => 'text',
                'required' => true,
                'enabled' => true,
            ],
            [
                'key' => 'address',
                'label' => 'آدرس کامل',
                'type' => 'textarea',
                'required' => true,
                'enabled' => true,
            ],
            [
                'key' => 'location',
                'label' => 'موقعیت مکانی (نقشه)',
                'type' => 'map',
                'required' => false,
                'enabled' => true,
            ],
        ];

        foreach ($fields as $field) {
            ProfileField::updateOrCreate(
                ['key' => $field['key']],
                $field
            );
        }
    }
}
