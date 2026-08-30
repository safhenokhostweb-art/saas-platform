<?php
namespace App\Services\ThemeBuilder\QualityGate;

/**
 * One structured finding from one ThemeQualityCheck. A single check may
 * return several of these (e.g. one per block, one per dependency) — the
 * gate never collapses findings into a single boolean, per Phase 2a's
 * requirement that checks "return structured results, not booleans only."
 */
final class ThemeQualityCheckResult
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_WARNING = 'warning';
    /** Honest "not checked" — the tooling/capability this would require is
     *  unavailable here. Never conflate with STATUS_PASSED. */
    public const STATUS_UNVERIFIED = 'unverified';

    /** Blocks a commercial release. */
    public const SEVERITY_BLOCKING = 'blocking';
    /** Recorded and visible, never blocks. */
    public const SEVERITY_WARNING = 'warning';
    /** Informational only (e.g. an unverified-capability disclosure). */
    public const SEVERITY_INFO = 'info';

    public function __construct(
        public readonly string $category,
        public readonly string $code,
        public readonly string $status,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $details = [],
    ) {
    }

    public static function pass(string $category, string $code, string $message, array $details = []): self
    {
        return new self($category, $code, self::STATUS_PASSED, self::SEVERITY_INFO, $message, $details);
    }

    public static function fail(string $category, string $code, string $message, array $details = [], string $severity = self::SEVERITY_BLOCKING): self
    {
        return new self($category, $code, self::STATUS_FAILED, $severity, $message, $details);
    }

    public static function warn(string $category, string $code, string $message, array $details = []): self
    {
        return new self($category, $code, self::STATUS_WARNING, self::SEVERITY_WARNING, $message, $details);
    }

    public static function unverified(string $category, string $code, string $message, array $details = []): self
    {
        return new self($category, $code, self::STATUS_UNVERIFIED, self::SEVERITY_INFO, $message, $details);
    }

    /** True only for a failed result that is also blocking severity. */
    public function isBlockingFailure(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->severity === self::SEVERITY_BLOCKING;
    }

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'code' => $this->code,
            'status' => $this->status,
            'severity' => $this->severity,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
