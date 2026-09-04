<?php
namespace App\Services\ThemeBuilder\Dependency;

use App\Models\ThemeReleaseDependency;
use RuntimeException;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;

/**
 * Phase 3G-B-3 — one proposed Theme→Extension PRODUCT dependency, deduped by
 * product identity (never one proposal per block occurrence — see
 * ThemeExtensionDependencyExtractor). Pure data; never persisted by this
 * class itself.
 *
 * `versionConstraint` is deliberately always null today: the current Theme
 * snapshot records only which `ext:*` block TYPES are used, never which
 * Extension release/version an author selected, so there is no truthful
 * source for a version requirement yet (ADR-045/3G-B-3 audit finding). This
 * class must never fabricate `*`, `latest`, `current`, or a guessed `>=`
 * bound — `null` is the existing, already-tested "unconstrained" contract
 * on ThemeReleaseDependency.version_constraint, reused honestly here rather
 * than invented.
 *
 * `isRequired` is always true for V1 per Product Owner policy (ADR-045):
 * extracted Extension dependencies default to required until an
 * optional-dependency authoring concept exists.
 */
final class ExtensionDependencyProposal
{
    /** @param string[] $elementTypes the ext:* types (of this product) that produced this proposal, sorted */
    public function __construct(
        public readonly string $productIdentity,
        public readonly array $elementTypes,
        public readonly bool $isRequired = true,
        public readonly ?string $versionConstraint = null,
    ) {
        if (!ExtensionProductIdentity::isValidIdentifier($productIdentity)) {
            throw new RuntimeException("ExtensionDependencyProposal product identity \"{$productIdentity}\" is not a valid \"{vendor-slug}/{package-slug}\" identifier.");
        }

        if ($elementTypes === []) {
            throw new RuntimeException('ExtensionDependencyProposal must be backed by at least one element type.');
        }
    }

    /**
     * Shape ThemeReleaseService::release()/attachDependencies() already
     * accepts for its `$dependencies` array — NOT wired into that service in
     * this phase (see ADR-045/3G-B-3 boundary), exposed only so a future
     * phase can pass this straight through without re-deriving the shape.
     */
    public function toReleaseDependencyArray(): array
    {
        return [
            'type' => ThemeReleaseDependency::TYPE_EXTENSION,
            'ref' => $this->productIdentity,
            'is_required' => $this->isRequired,
            'version_constraint' => $this->versionConstraint,
            'metadata' => ['element_types' => $this->elementTypes],
        ];
    }
}
