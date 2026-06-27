<?php
namespace App\Services\Installer;

class InstallerService
{
    const MODE_FRESH   = 'fresh';
    const MODE_RESTORE = 'restore';
    const MODE_UPGRADE = 'upgrade';

    const LOCK_FILE = 'install.lock';

    const FRESH_STEPS = [
        'env_check',
        'database',
        'environment',
        'platform',
        'infrastructure',
        'validation',
        'finish',
    ];

    const RESTORE_STEPS = [
        'source_select',
        'verify',
        'restore',
        'finish',
    ];

    const UPGRADE_STEPS = [
        'snapshot',
        'upgrade',
        'validate',
        'finish',
    ];

    // ─── Lock Management ─────────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return file_exists(storage_path(self::LOCK_FILE));
    }

    public function lock(array $metadata = []): void
    {
        $data = array_merge([
            'locked_at'    => now()->toISOString(),
            'mode'         => 'unknown',
            'completed_at' => now()->toISOString(),
            'version'      => config('app.version', '1.0.0'),
        ], $metadata);

        file_put_contents(storage_path(self::LOCK_FILE), json_encode($data, JSON_PRETTY_PRINT));
    }

    public function unlock(): void
    {
        $path = storage_path(self::LOCK_FILE);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function getLockData(): ?array
    {
        $path = storage_path(self::LOCK_FILE);
        if (!file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path), true) ?? [];
    }

    // ─── Session State ───────────────────────────────────────────────────────

    private string $stateKey = 'platform_installer_state';

    public function getState(): array
    {
        return session($this->stateKey, [
            'mode'            => null,
            'current_step'    => null,
            'completed_steps' => [],
            'data'            => [],
            'started_at'      => null,
        ]);
    }

    public function saveState(array $state): void
    {
        session([$this->stateKey => $state]);
    }

    public function clearState(): void
    {
        session()->forget($this->stateKey);
    }

    public function initMode(string $mode): void
    {
        $steps = $this->getStepsForMode($mode);
        $this->saveState([
            'mode'            => $mode,
            'current_step'    => $steps[0] ?? null,
            'completed_steps' => [],
            'data'            => [],
            'started_at'      => now()->toISOString(),
        ]);
    }

    // ─── Step Navigation ─────────────────────────────────────────────────────

    public function getStepsForMode(string $mode): array
    {
        return match($mode) {
            self::MODE_FRESH   => self::FRESH_STEPS,
            self::MODE_RESTORE => self::RESTORE_STEPS,
            self::MODE_UPGRADE => self::UPGRADE_STEPS,
            default            => [],
        };
    }

    public function getStepIndex(string $mode, string $step): int
    {
        $steps = $this->getStepsForMode($mode);
        $idx   = array_search($step, $steps);
        return $idx === false ? 0 : (int) $idx;
    }

    public function getNextStep(string $mode, string $currentStep): ?string
    {
        $steps = $this->getStepsForMode($mode);
        $idx   = array_search($currentStep, $steps);
        if ($idx === false || $idx >= count($steps) - 1) {
            return null;
        }
        return $steps[$idx + 1];
    }

    public function getPreviousStep(string $mode, string $currentStep): ?string
    {
        $steps = $this->getStepsForMode($mode);
        $idx   = array_search($currentStep, $steps);
        if ($idx === false || $idx === 0) {
            return null;
        }
        return $steps[$idx - 1];
    }

    public function markStepCompleted(string $step): void
    {
        $state = $this->getState();
        if (!in_array($step, $state['completed_steps'])) {
            $state['completed_steps'][] = $step;
        }
        $this->saveState($state);
    }

    public function isStepCompleted(string $step): bool
    {
        return in_array($step, $this->getState()['completed_steps'] ?? []);
    }

    public function saveStepData(string $step, array $data): void
    {
        $state = $this->getState();
        $state['data'][$step] = $data;
        $this->saveState($state);
    }

    public function getStepData(string $step): array
    {
        return $this->getState()['data'][$step] ?? [];
    }

    // ─── Step Labels (Persian) ───────────────────────────────────────────────

    public function getStepLabel(string $step): string
    {
        return [
            'env_check'       => 'بررسی محیط',
            'database'        => 'پایگاه داده',
            'environment'     => 'تنظیمات محیط',
            'platform'        => 'نصب پلتفرم',
            'infrastructure'  => 'زیرساخت',
            'validation'      => 'اعتبارسنجی',
            'finish'          => 'اتمام',
            'source_select'   => 'انتخاب منبع',
            'verify'          => 'تأیید پشتیبان',
            'restore'         => 'بازیابی',
            'snapshot'        => 'اسنپ‌شات',
            'upgrade'         => 'ارتقاء',
            'validate'        => 'اعتبارسنجی',
        ][$step] ?? $step;
    }

    public function getModeLabel(string $mode): string
    {
        return match($mode) {
            self::MODE_FRESH   => 'نصب تازه',
            self::MODE_RESTORE => 'بازیابی پلتفرم',
            self::MODE_UPGRADE => 'ارتقاء پلتفرم',
            default            => $mode,
        };
    }
}
