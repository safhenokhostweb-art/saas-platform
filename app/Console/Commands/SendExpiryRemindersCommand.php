<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderService;
use Illuminate\Console\Command;

class SendExpiryRemindersCommand extends Command
{
    protected $signature = 'expiry:send-reminders';

    protected $description = 'ارسال خودکار اعلان هشدار انقضای پشتیبانی، هاست و دامنه (۱ ماه قبل و هر ۱۰ روز)';

    public function handle(ExpiryReminderService $service): int
    {
        $sent = $service->run();
        $total = $sent['support'] + $sent['host'] + $sent['domain'];
        if ($this->output->isVerbose() || $total > 0) {
            $this->info("اعلان ارسال‌شده: پشتیبانی {$sent['support']}, هاست {$sent['host']}, دامنه {$sent['domain']}.");
        }
        return self::SUCCESS;
    }
}
