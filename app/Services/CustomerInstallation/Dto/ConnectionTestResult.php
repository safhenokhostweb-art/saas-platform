<?php

namespace App\Services\CustomerInstallation\Dto;

class ConnectionTestResult
{
    public function __construct(
        public readonly bool    $connected,
        public readonly string  $os,
        public readonly string  $phpVersion,
        public readonly array   $phpExtensions,
        public readonly int     $diskFreeBytes,
        public readonly bool    $mysqlAccessible,
        public readonly bool    $supervisorPresent,
        public readonly int     $pingMs,
        public readonly array   $warnings,
        public readonly ?string $fingerprint,
        public readonly ?string $error,
    ) {}

    public static function failure(string $error): self
    {
        return new self(
            connected:         false,
            os:                '',
            phpVersion:        '',
            phpExtensions:     [],
            diskFreeBytes:     0,
            mysqlAccessible:   false,
            supervisorPresent: false,
            pingMs:            0,
            warnings:          [],
            fingerprint:       null,
            error:             $error,
        );
    }

    public function toArray(): array
    {
        return [
            'connected'          => $this->connected,
            'os'                 => $this->os,
            'php_version'        => $this->phpVersion,
            'php_extensions'     => $this->phpExtensions,
            'disk_free_bytes'    => $this->diskFreeBytes,
            'mysql_accessible'   => $this->mysqlAccessible,
            'supervisor_present' => $this->supervisorPresent,
            'ping_ms'            => $this->pingMs,
            'warnings'           => $this->warnings,
            'fingerprint'        => $this->fingerprint,
            'error'              => $this->error,
        ];
    }
}
