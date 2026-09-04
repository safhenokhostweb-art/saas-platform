<?php
namespace App\Services\ThemeBuilder\Dependency;

/**
 * Phase 3G-B-3 — the fail-closed outcome of asking "which Extension PRODUCT
 * owns this ext:* element type?" (ExtensionElementOwnershipResolver). Three
 * states only, never a guess:
 *
 *   - resolved:   exactly one Extension product's manifest(s) ever claimed
 *                 this element type.
 *   - unresolved: zero Extension products have.
 *   - ambiguous:  two or more different Extension products have — this is
 *                 never arbitrarily resolved to "latest"/"first"/"highest
 *                 id"; the caller must see all candidates and fail closed.
 */
final class ElementOwnershipResult
{
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_UNRESOLVED = 'unresolved';
    public const STATUS_AMBIGUOUS = 'ambiguous';

    /** @param string[] $candidateProductIdentities */
    private function __construct(
        public readonly string $elementType,
        public readonly string $status,
        public readonly ?string $productIdentity,
        public readonly array $candidateProductIdentities,
    ) {
    }

    public static function resolved(string $elementType, string $productIdentity): self
    {
        return new self($elementType, self::STATUS_RESOLVED, $productIdentity, [$productIdentity]);
    }

    public static function unresolved(string $elementType): self
    {
        return new self($elementType, self::STATUS_UNRESOLVED, null, []);
    }

    /** @param string[] $candidateProductIdentities two or more, sorted */
    public static function ambiguous(string $elementType, array $candidateProductIdentities): self
    {
        return new self($elementType, self::STATUS_AMBIGUOUS, null, $candidateProductIdentities);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isUnresolved(): bool
    {
        return $this->status === self::STATUS_UNRESOLVED;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === self::STATUS_AMBIGUOUS;
    }
}
