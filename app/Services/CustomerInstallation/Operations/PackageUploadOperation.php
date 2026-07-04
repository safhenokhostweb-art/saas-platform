<?php

namespace App\Services\CustomerInstallation\Operations;

use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Enums\DeploymentCapability;
use Throwable;

final class PackageUploadOperation
{
    public function execute(
        DeploymentDriverInterface $driver,
        string $localPath,
        string $remotePath,
    ): mixed {
        $unsupported = $this->unsupportedReason($driver);

        if ($unsupported !== null) {
            return $unsupported;
        }

        $sourcePath = realpath($localPath);
        $releaseRoot = realpath((string) config('customer_installation.package.local_temp_path'));
        $rootPrefix = $releaseRoot === false ? null : rtrim($releaseRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($sourcePath === false
            || $rootPrefix === null
            || !str_starts_with($sourcePath, $rootPrefix)
            || !is_file($sourcePath)
            || strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) !== 'zip'
            || !$this->isZipArchive($sourcePath)) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'The immutable release ZIP is unavailable from the configured package cache.',
            );
        }

        $documentRoot = str_replace('\\', '/', trim((string) config('customer_installation.hosting.document_root')));
        if ($documentRoot === ''
            || str_contains($documentRoot, chr(0))
            || preg_match('#(^|/)\.\.(/|$)#', $documentRoot) === 1
            || preg_match('#^/?var/www(?:/|$)#i', $documentRoot) === 1) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'The configured hosting document_root does not produce a safe package target path.',
            );
        }

        $targetPath = rtrim($documentRoot, '/') . '/' . basename($sourcePath);

        try {
            return $driver->uploadPackage($sourcePath, $targetPath);
        } catch (Throwable) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'The cPanel package upload failed safely.',
                [DeploymentCapability::FILE_UPLOAD->value],
            );
        }
    }

    private function isZipArchive(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }

        $archive = new \ZipArchive();
        $opened = @$archive->open($path, \ZipArchive::RDONLY);

        if ($opened !== true) {
            return false;
        }

        $archive->close();

        return true;
    }
    private function unsupportedReason(
        DeploymentDriverInterface $driver,
    ): ?UnsupportedOperationResult {
        if (!method_exists($driver, 'uploadPackage')) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'The driver has no native package-upload operation.',
            );
        }

        if (!method_exists($driver, 'probeCapabilities')) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'The driver cannot provide fresh file-upload capability evidence.',
                [DeploymentCapability::FILE_UPLOAD->value],
            );
        }

        try {
            $probe = $driver->probeCapabilities();
        } catch (Throwable) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'The driver could not verify file-upload capability.',
                [DeploymentCapability::FILE_UPLOAD->value],
            );
        }

        if (!method_exists($probe, 'hasFreshEvidenceFor')
            || !$probe->hasFreshEvidenceFor([DeploymentCapability::FILE_UPLOAD])) {
            return new UnsupportedOperationResult(
                'UPLOAD_PACKAGE',
                'Fresh file-upload capability evidence is unavailable.',
                [DeploymentCapability::FILE_UPLOAD->value],
            );
        }

        return null;
    }
}
