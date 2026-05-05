<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\ModuleFeature;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        Module::firstOrCreate(
            ['slug' => 'blog'],
            [
                'title' => 'وبلاگ',
                'description' => 'ماژول وبلاگ و مطالب',
                'is_active' => true,
            ]
        );

        Module::firstOrCreate(
            ['slug' => 'shop'],
            [
                'title' => 'فروشگاه',
                'description' => 'ماژول فروشگاه آنلاین',
                'is_active' => true,
            ]
        );

        $installment = Module::firstOrCreate(
            ['slug' => 'installment'],
            [
                'title' => 'پرداخت اقساطی',
                'description' => 'پرداخت اقساطی با سرویس‌دهنده‌ها',
                'is_active' => true,
            ]
        );

        ModuleFeature::firstOrCreate([
            'module_id' => $installment->id,
            'key' => 'azki_vam',
        ], [
            'title' => 'ازکی وام',
            'is_active' => true,
        ]);

        ModuleFeature::firstOrCreate([
            'module_id' => $installment->id,
            'key' => 'tara',
        ], [
            'title' => 'تارا',
            'is_active' => true,
        ]);

        ModuleFeature::firstOrCreate([
            'module_id' => $installment->id,
            'key' => 'snap_pay',
        ], [
            'title' => 'اسنپ پی',
            'is_active' => true,
        ]);
    }
}
