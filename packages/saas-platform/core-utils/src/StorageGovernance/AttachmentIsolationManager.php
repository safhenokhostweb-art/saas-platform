<?php

namespace SaaSPlatform\CoreUtils\StorageGovernance;

class AttachmentIsolationManager
{
    private const PATH_SEPARATOR = '/';

    public function tenantPath(int $tenantId, string $subPath = ''): string
    {
        $base = 'tenants' . self::PATH_SEPARATOR . $tenantId;
        return $subPath ? $base . self::PATH_SEPARATOR . ltrim($subPath, '/') : $base;
    }

    public function isWithinTenantScope(int $tenantId, string $path): bool
    {
        $tenantPrefix = 'tenants/' . $tenantId . '/';
        return str_starts_with($path, $tenantPrefix);
    }

    public function extractTenantFromPath(string $path): ?int
    {
        if (!str_starts_with($path, 'tenants/')) {
            return null;
        }
        $parts = explode('/', $path, 3);
        return isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null;
    }

    public function isCrossTenantAccess(int $requestingTenantId, string $path): bool
    {
        $owningTenant = $this->extractTenantFromPath($path);
        return $owningTenant !== null && $owningTenant !== $requestingTenantId;
    }

    public function sanitizePath(string $path): string
    {
        // Prevent path traversal
        $path = str_replace(['../', './', '..\\', '.\\'], '', $path);
        return ltrim($path, '/');
    }

    public function validatePath(int $tenantId, string $path): array
    {
        $sanitized = $this->sanitizePath($path);

        if ($this->isCrossTenantAccess($tenantId, $sanitized)) {
            return ['valid' => false, 'reason' => 'cross_tenant_access'];
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $sanitized)) {
            return ['valid' => false, 'reason' => 'invalid_characters'];
        }

        return ['valid' => true, 'sanitized_path' => $sanitized];
    }

    public function allowedExtensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'csv', 'xlsx', 'docx', 'zip', 'txt'];
    }

    public function isAllowedExtension(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $this->allowedExtensions(), true);
    }
}
