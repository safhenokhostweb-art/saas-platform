<?php

namespace App\Services;

use App\Models\PlatformSiteAgent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSiteAgentService
{
    /**
     * ایجاد یا به‌روزرسانی agent ادمین پلتفرم در دیتابیس سایت مشتری.
     *
     * @return array{agent: PlatformSiteAgent, password: string, is_new: bool}
     */
    public function provision(Site $site, string $mobile, User $provisionedBy, ?string $notes = null): array
    {
        $password = $this->generatePassword();
        $conn = $this->openSiteConnection($site);

        $existingAgent = PlatformSiteAgent::where('site_id', $site->id)->first();

        if ($existingAgent) {
            // به‌روزرسانی رمز عبور در دیتابیس مشتری
            $conn->table('users')
                ->where('id', $existingAgent->customer_site_user_id)
                ->update([
                    'password'   => Hash::make($password),
                    'is_admin'   => 1,
                    'updated_at' => now(),
                ]);

            $existingAgent->update([
                'agent_password_enc' => Crypt::encryptString($password),
                'is_active'          => true,
                'notes'              => $notes,
            ]);

            $this->logToCustomerSite($conn, $existingAgent->customer_site_user_id, 'platform_agent.reprovisioned', 'رمز عبور ادمین پلتفرم بازنشانی شد');

            return ['agent' => $existingAgent, 'password' => $password, 'is_new' => false];
        }

        // بررسی وجود کاربر با همین موبایل
        $existingUser = $conn->table('users')->where('mobile', $mobile)->first();
        $hasAgentCol = $conn->getSchemaBuilder()->hasColumn('users', 'is_platform_agent');

        if ($existingUser) {
            $updateData = [
                'is_admin'   => 1,
                'password'   => Hash::make($password),
                'updated_at' => now(),
            ];
            if ($hasAgentCol) {
                $updateData['is_platform_agent'] = 1;
            }
            $conn->table('users')->where('id', $existingUser->id)->update($updateData);
            $userId = $existingUser->id;
        } else {
            $insertData = [
                'name'       => 'پشتیبان پلتفرم',
                'mobile'     => $mobile,
                'password'   => Hash::make($password),
                'is_admin'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasAgentCol) {
                $insertData['is_platform_agent'] = 1;
            }
            $userId = $conn->table('users')->insertGetId($insertData);
        }

        $agent = PlatformSiteAgent::create([
            'site_id'                => $site->id,
            'provisioned_by'         => $provisionedBy->id,
            'mobile'                 => $mobile,
            'agent_password_enc'     => Crypt::encryptString($password),
            'customer_site_user_id'  => $userId,
            'is_active'              => true,
            'provisioned_at'         => now(),
            'notes'                  => $notes,
        ]);

        $this->logToCustomerSite($conn, $userId, 'platform_agent.provisioned', 'ادمین پلتفرم برای این سایت ثبت شد');

        return ['agent' => $agent, 'password' => $password, 'is_new' => true];
    }

    /**
     * لغو دسترسی agent — کاربر در سایت مشتری غیرفعال می‌شود.
     */
    public function revoke(PlatformSiteAgent $agent): void
    {
        $site = $agent->site;
        try {
            $conn = $this->openSiteConnection($site);
            $conn->table('users')
                ->where('id', $agent->customer_site_user_id)
                ->update(['is_admin' => 0, 'is_platform_agent' => 0, 'updated_at' => now()]);

            $this->logToCustomerSite($conn, $agent->customer_site_user_id, 'platform_agent.revoked', 'دسترسی ادمین پلتفرم لغو شد');
        } catch (\Throwable) {
            // اگر دیتابیس مشتری در دسترس نباشد، فقط لاگ platform به‌روز می‌شود
        }

        $agent->update(['is_active' => false]);
    }

    /**
     * ثبت آخرین دسترسی (فراخوانی دستی از controller پلتفرم).
     */
    public function recordAccess(PlatformSiteAgent $agent, Site $site): void
    {
        $agent->update(['last_access_logged_at' => now()]);

        try {
            $conn = $this->openSiteConnection($site);
            $this->logToCustomerSite($conn, $agent->customer_site_user_id, 'platform_agent.access_recorded', 'ثبت دسترسی ادمین پلتفرم از پنل مدیریت');
        } catch (\Throwable) {
        }
    }

    /**
     * بازگرداندن رمز عبور رمزنگاری‌شده agent.
     */
    public function decryptPassword(PlatformSiteAgent $agent): ?string
    {
        return $agent->getDecryptedPassword();
    }

    /**
     * آزمایش اتصال به دیتابیس سایت.
     */
    public function testConnection(Site $site): bool
    {
        try {
            $this->openSiteConnection($site)->select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ─────────────────────────────────────────────

    private function openSiteConnection(Site $site): \Illuminate\Database\Connection
    {
        if (! $site->db_host || ! $site->db_name) {
            throw new \RuntimeException('اطلاعات اتصال دیتابیس سایت "' . $site->title . '" تنظیم نشده است.');
        }

        $connKey = 'site_agent_' . $site->id;

        Config::set("database.connections.{$connKey}", [
            'driver'    => 'mysql',
            'host'      => $site->db_host,
            'port'      => '3306',
            'database'  => $site->db_name,
            'username'  => $site->db_user,
            'password'  => $site->db_password ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]);

        DB::purge($connKey);
        return DB::connection($connKey);
    }

    private function logToCustomerSite(\Illuminate\Database\Connection $conn, int $adminUserId, string $action, string $description): void
    {
        if (! $conn->getSchemaBuilder()->hasTable('admin_activity_log')) {
            return;
        }
        try {
            $conn->table('admin_activity_log')->insert([
                'admin_user_id' => $adminUserId,
                'action'        => $action,
                'description'   => $description,
                'properties'    => json_encode(['source' => 'platform']),
                'ip_address'    => request()?->ip(),
                'user_agent'    => request()?->userAgent(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    private function generatePassword(): string
    {
        return Str::random(8) . strtoupper(Str::random(4)) . rand(10, 99) . '!';
    }
}
