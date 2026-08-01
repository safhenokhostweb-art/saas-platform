<?php

namespace SaaSPlatform\Modules\Crm\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Crm\Services\CrmTaskReminderService;

class SendCrmTaskRemindersCommand extends Command
{
    protected $signature = 'crm:send-task-reminders';

    protected $description = 'ارسال یادآوری برای وظایف نزدیک به سررسید، عقب‌افتاده و معاملات بدون فعالیت';

    public function handle(CrmTaskReminderService $service): int
    {
        $counts = $service->run();

        $this->info("سررسید نزدیک: {$counts['deadline']} — عقب‌افتاده: {$counts['overdue']} — معامله بدون فعالیت: {$counts['stale_opportunity']}");

        return self::SUCCESS;
    }
}
