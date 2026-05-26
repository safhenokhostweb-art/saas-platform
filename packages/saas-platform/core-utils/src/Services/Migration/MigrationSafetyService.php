<?php

namespace SaaSPlatform\CoreUtils\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Governs safe schema evolution in production multi-tenant environments.
 *
 * Detects destructive, blocking, and tenant-sensitive operations
 * before migrations are executed.
 */
class MigrationSafetyService
{
    private const DANGEROUS_PATTERNS = [
        '/\$table->dropColumn\(/i'          => ['risk' => 'CRITICAL', 'label' => 'Column drop'],
        '/Schema::drop\(/i'                  => ['risk' => 'CRITICAL', 'label' => 'Table drop'],
        '/Schema::dropIfExists\(/i'          => ['risk' => 'HIGH',     'label' => 'Table drop (if exists)'],
        '/->dropForeign\(/i'                 => ['risk' => 'MEDIUM',   'label' => 'Foreign key drop'],
        '/->dropIndex\(/i'                   => ['risk' => 'MEDIUM',   'label' => 'Index drop'],
        '/->dropUnique\(/i'                  => ['risk' => 'MEDIUM',   'label' => 'Unique constraint drop'],
        '/->rename\(/i'                      => ['risk' => 'HIGH',     'label' => 'Column rename'],
        '/->change\(\)/i'                    => ['risk' => 'HIGH',     'label' => 'Column type change'],
        '/->enum\(/i'                        => ['risk' => 'MEDIUM',   'label' => 'Enum column (mutation risk)'],
        '/->integer\(.*\)->nullable\(false\)/i' => ['risk' => 'HIGH', 'label' => 'Non-nullable integer'],
        '/truncate\(\)/i'                    => ['risk' => 'CRITICAL', 'label' => 'Table truncate'],
        '/->unsignedBigInteger\([^)]+\)(?!.*->nullable)/' => ['risk' => 'HIGH', 'label' => 'Non-nullable bigint add'],
    ];

    private const TENANT_SENSITIVE_TABLES = [
        'tickets', 'ticket_replies', 'form_submissions', 'form_site',
        'sites', 'site_modules', 'site_settings',
    ];

    public function analyzeMigrationFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['error' => "File not found: {$filePath}"];
        }

        $content  = file_get_contents($filePath);
        $filename = basename($filePath);
        $findings = [];

        foreach (self::DANGEROUS_PATTERNS as $pattern => $meta) {
            if (preg_match($pattern, $content)) {
                $findings[] = [
                    'risk'    => $meta['risk'],
                    'label'   => $meta['label'],
                    'pattern' => $pattern,
                ];
            }
        }

        $tenantSensitive = $this->detectTenantSensitive($content);
        $irreversible    = $this->detectIrreversible($content);

        return [
            'file'             => $filename,
            'path'             => $filePath,
            'findings'         => $findings,
            'tenant_sensitive' => $tenantSensitive,
            'irreversible'     => $irreversible,
            'risk_level'       => $this->aggregateRisk($findings),
            'safe'             => empty($findings) && !$irreversible,
        ];
    }

    public function analyzePendingMigrations(): array
    {
        $migrationsPath = database_path('migrations');
        $ran            = DB::table('migrations')->pluck('migration')->toArray();
        $results        = [];

        foreach (glob($migrationsPath . '/*.php') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (!in_array($name, $ran)) {
                $results[$name] = $this->analyzeMigrationFile($file);
            }
        }

        return $results;
    }

    public function hasBlockingOperations(string $filePath): bool
    {
        $analysis = $this->analyzeMigrationFile($filePath);

        $blockingRisks = array_filter(
            $analysis['findings'] ?? [],
            fn($f) => in_array($f['risk'], ['CRITICAL', 'HIGH'])
        );

        return !empty($blockingRisks);
    }

    private function detectTenantSensitive(string $content): bool
    {
        foreach (self::TENANT_SENSITIVE_TABLES as $table) {
            if (str_contains($content, "'{$table}'") || str_contains($content, "\"{$table}\"")) {
                return true;
            }
        }
        return false;
    }

    private function detectIrreversible(string $content): bool
    {
        // A migration is irreversible if down() is empty or throws
        if (preg_match('/function down\(\)[^{]*\{[^}]*\}/s', $content, $match)) {
            $body = trim($match[0]);
            // Empty down method
            if (preg_match('/function down\(\)[^{]*\{\s*\}/s', $content)) {
                return true;
            }
            // down() that throws
            if (str_contains($body, 'throw new') || str_contains($body, '//')) {
                return false; // has code — not irreversible by this check
            }
        }
        return false;
    }

    private function aggregateRisk(array $findings): string
    {
        $levels = array_column($findings, 'risk');
        if (in_array('CRITICAL', $levels)) return 'CRITICAL';
        if (in_array('HIGH', $levels))     return 'HIGH';
        if (in_array('MEDIUM', $levels))   return 'MEDIUM';
        return 'LOW';
    }
}
