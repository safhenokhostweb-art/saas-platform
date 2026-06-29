<?php

namespace App\Services\CustomerDeletion\Dto;

enum DeletePolicy: string
{
    case NEVER_DELETE_BACKUP   = 'never_delete_backup';
    case DELETE_LOCAL_BACKUPS  = 'delete_local_backups';
    case DELETE_REMOTE_BACKUPS = 'delete_remote_backups';
    case DELETE_EVERYTHING     = 'delete_everything';

    public function label(): string
    {
        return match($this) {
            self::NEVER_DELETE_BACKUP   => 'حذف مشتری — حفظ تمام پشتیبان‌ها',
            self::DELETE_LOCAL_BACKUPS  => 'حذف مشتری + پشتیبان‌های محلی',
            self::DELETE_REMOTE_BACKUPS => 'حذف مشتری + پشتیبان‌های ابری (آروان)',
            self::DELETE_EVERYTHING     => 'حذف کامل — شامل همه پشتیبان‌ها',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::NEVER_DELETE_BACKUP   => 'پشتیبان‌های آروان و محلی حفظ می‌شوند. داده قابل بازیابی است.',
            self::DELETE_LOCAL_BACKUPS  => 'پشتیبان‌های آروان حفظ می‌شوند. فایل‌های محلی حذف می‌شوند.',
            self::DELETE_REMOTE_BACKUPS => 'پشتیبان‌های محلی حفظ می‌شوند. آبجکت‌های آروان حذف می‌شوند.',
            self::DELETE_EVERYTHING     => 'هیچ پشتیبانی باقی نمی‌ماند. غیرقابل بازگشت.',
        };
    }

    public function shouldDeleteLocalBackups(): bool
    {
        return in_array($this, [self::DELETE_LOCAL_BACKUPS, self::DELETE_EVERYTHING]);
    }

    public function shouldDeleteRemoteBackups(): bool
    {
        return in_array($this, [self::DELETE_REMOTE_BACKUPS, self::DELETE_EVERYTHING]);
    }

    public function isDestructive(): bool
    {
        return $this === self::DELETE_EVERYTHING;
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    public static function options(): array
    {
        return array_map(fn(self $p) => [
            'value'       => $p->value,
            'label'       => $p->label(),
            'description' => $p->description(),
        ], self::cases());
    }
}
