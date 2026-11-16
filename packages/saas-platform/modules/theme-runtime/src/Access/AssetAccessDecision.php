<?php

namespace SaaSPlatform\ThemeRuntime\Access;

/**
 * ADR-087: the answer for one asset and one site. `available` may be
 * used; `locked` is listed disabled with a badge (an upgrade path);
 * `hidden` is not listed at all.
 */
final class AssetAccessDecision
{
    public const STATE_AVAILABLE = 'available';

    public const STATE_LOCKED = 'locked';

    public const STATE_HIDDEN = 'hidden';

    public const REASON_PUBLIC = 'public';

    public const REASON_GRANT = 'grant';

    public const REASON_ENTITLED = 'entitled';

    public const REASON_PLATFORM_DESIGNER = 'platform_designer';

    public const REASON_VERTICAL_MISMATCH = 'vertical_mismatch';

    public const REASON_ENTITLEMENT_REQUIRED = 'entitlement_required';

    public const REASON_GRANT_REQUIRED = 'grant_required';

    public const BADGE_EXCLUSIVE = 'اختصاصی لایسنس';

    public const BADGE_PURCHASE = 'نیازمند خرید';

    public function __construct(
        public readonly string $state,
        public readonly string $reason,
        public readonly bool $restricted,
        public readonly ?string $familyLabel = null,
    ) {}

    public function allowed(): bool
    {
        return $this->state === self::STATE_AVAILABLE;
    }

    public function visible(): bool
    {
        return $this->state !== self::STATE_HIDDEN;
    }

    /** The editor badge, or null for an ordinary public asset. */
    public function badge(): ?string
    {
        if (!$this->restricted) {
            return null;
        }

        return $this->reason === self::REASON_ENTITLEMENT_REQUIRED ? self::BADGE_PURCHASE : self::BADGE_EXCLUSIVE;
    }

    /** One sentence for a tooltip or a refusal. */
    public function message(): ?string
    {
        return match ($this->reason) {
            self::REASON_ENTITLEMENT_REQUIRED => 'این مورد با خرید لایسنس' . ($this->familyLabel ? ' «' . $this->familyLabel . '»' : '') . ' فعال می‌شود.',
            self::REASON_GRANT_REQUIRED, self::REASON_VERTICAL_MISMATCH => 'این مورد اختصاصی است و فقط با اختصاص از پنل مرکزی پلتفرم فعال می‌شود.',
            default => null,
        };
    }

    /** The shape both editors read (palette items and template cards). */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'reason' => $this->reason,
            'restricted' => $this->restricted,
            'locked' => $this->state === self::STATE_LOCKED,
            'badge' => $this->badge(),
            'message' => $this->message(),
            'family_label' => $this->familyLabel,
        ];
    }
}
