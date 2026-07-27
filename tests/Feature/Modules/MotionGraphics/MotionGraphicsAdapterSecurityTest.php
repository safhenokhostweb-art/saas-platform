<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Models\User;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use Tests\TestCase;

/**
 * Task scope item 9 (Security): a Customer cannot access Master Asset
 * management or AI metadata, and cannot act across a tenant boundary —
 * see docs/motion-saas-integration/AUTHORIZATION_MATRIX.md and
 * docs/motion-saas-integration/RUNTIME_PROJECTION.md.
 */
class MotionGraphicsAdapterSecurityTest extends TestCase
{
    private function customer(int $siteId): User
    {
        $user = new User();
        $user->id = 100 + $siteId;
        $user->is_admin = false;
        $user->site_id = $siteId;

        return $user;
    }

    /**
     * Binds a trusted "current site" so ability checks can be exercised
     * in isolation from tenant-context resolution (which in production
     * comes from the request's domain — see App\Services\TenantContext).
     */
    private function bindTrustedSite(int $siteId): void
    {
        $this->app->instance(TenantContextContract::class, new class($siteId) implements TenantContextContract {
            public function __construct(private readonly int $siteId) {}
            public function current(): ?object { return $this->site(); }
            public function type(): ?string { return 'site'; }
            public function isReseller(): bool { return false; }
            public function isSite(): bool { return true; }
            public function reseller(): ?object { return null; }
            public function site(): ?object { return (object) ['id' => $this->siteId]; }
            public function id(): ?int { return $this->siteId; }
            public function hasTenant(): bool { return true; }
            public function hasFeature(string $feature): bool { return (bool) config("feature-flags.{$feature}"); }
        });
    }

    public function test_a_customer_cannot_create_a_master_asset(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);
        $customer = $this->customer(1);
        $this->actingAs($customer);
        $this->bindTrustedSite(1);

        $decision = $this->app->make(MotionAuthorizationInterface::class)
            ->authorize('user:'.$customer->id, 'motion.create', 'motion-1', (string) $customer->site_id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('ability_denied', $decision->code);
    }

    public function test_a_customer_cannot_publish_or_rollback(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);
        $customer = $this->customer(1);
        $this->actingAs($customer);
        $this->bindTrustedSite(1);

        $authorization = $this->app->make(MotionAuthorizationInterface::class);

        $this->assertFalse($authorization->authorize('user:'.$customer->id, 'motion.publish', 'motion-1', (string) $customer->site_id)->allowed);
        $this->assertFalse($authorization->authorize('user:'.$customer->id, 'motion.rollback', 'motion-1', (string) $customer->site_id)->allowed);
    }

    public function test_a_customer_from_one_site_cannot_authorize_against_another_sites_id(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);
        $customerA = $this->customer(1);
        $this->actingAs($customerA);
        $this->bindTrustedSite(1);

        $decision = $this->app->make(MotionAuthorizationInterface::class)
            ->authorize('user:'.$customerA->id, 'motion.use', 'motion-1', '2');

        $this->assertFalse($decision->allowed);
        $this->assertSame('tenant_context_mismatch', $decision->code);
    }

    public function test_runtime_projection_data_can_never_carry_ai_metadata_or_source_definitions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Simulates an attempt to leak AI/authoring internals through the
        // allowlisted theme_tokens field of a runtime projection.
        ForbiddenFieldGuard::assertClean(['ai_metadata' => ['prompt' => 'generate a logo']], 'theme_tokens');
    }

    public function test_runtime_projection_data_can_never_carry_ownership_or_credential_internals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ForbiddenFieldGuard::assertClean(['credentials' => 'secret-key'], 'placement_rules');
    }
}
