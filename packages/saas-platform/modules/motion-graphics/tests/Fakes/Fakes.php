<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Fakes;

use DateTimeImmutable;
use SaaSPlatform\Modules\MotionGraphics\Contracts\ClockContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAiProviderContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPreviewGeneratorContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiRequest;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiResult;

final readonly class TestActor { public function __construct(public string $id, public bool $platformAdmin = false) {} }
final class FakePlatformAdminResolver implements PlatformAdminResolverContract {
    public function isPlatformAdmin(mixed $actor): bool { return $actor instanceof TestActor && $actor->platformAdmin; }
    public function actorIdentifier(mixed $actor): string { return $actor instanceof TestActor ? $actor->id : 'unknown'; }
}
final class FakePermissionGateway implements MotionPermissionGatewayContract {
    public function allows(mixed $actor, string $ability): bool { return $actor instanceof TestActor && $actor->platformAdmin; }
}
final class FakeSiteResolver implements SiteResolverContract {
    public array $sites = ['site-a', 'site-b', 'site-c'];
    public ?string $current = null;
    public function exists(string $siteIdentifier): bool { return in_array($siteIdentifier, $this->sites, true); }
    public function currentSiteIdentifier(): ?string { return $this->current; }
}
final class FakeAiProvider implements MotionAiProviderContract {
    public ?MotionAiResult $next = null;
    public function providerName(): string { return 'fake-provider'; }
    public function generate(MotionAiRequest $request): MotionAiResult {
        return $this->next ?? MotionAiResult::success($request->sourceDefinition, ['model' => 'fake-model']);
    }
}
final class FakePreviewGenerator implements MotionPreviewGeneratorContract {
    public function generate(array $source, array $assets, string $versionPath): array {
        return ['status' => 'generated', 'url' => '/'.$versionPath.'/preview.html'];
    }
}
final class RecordingAuditLogger implements MotionAuditLoggerContract {
    public array $events = [];
    public function record(string $event, array $context = []): void { $this->events[] = [$event, $context]; }
}
final class RecordingDeploymentGateway implements MotionDeploymentGatewayContract {
    public array $published = [];
    public function published(string $motionUuid, int $version): void { $this->published[] = [$motionUuid, $version]; }
}
final class FrozenClock implements ClockContract {
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-07-13T12:00:00+03:30'); }
}
