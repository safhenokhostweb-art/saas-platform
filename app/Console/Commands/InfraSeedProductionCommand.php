<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\InfrastructureProductionSeeder;

class InfraSeedProductionCommand extends Command
{
    protected $signature   = 'infra:seed-production {--force : اجرا بدون تایید}';
    protected $description  = 'ثبت اطلاعات واقعی سرور پروداکشن در ماژول زیرساخت';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('اطلاعات سرور پروداکشن در دیتابیس ثبت/بروزرسانی شود؟')) {
            $this->info('لغو شد.');
            return 0;
        }

        $this->info('شروع ثبت اطلاعات زیرساخت...');
        $seeder = new InfrastructureProductionSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('✅ کامل شد. برای مشاهده به /admin/infrastructure مراجعه کنید.');
        return 0;
    }
}
