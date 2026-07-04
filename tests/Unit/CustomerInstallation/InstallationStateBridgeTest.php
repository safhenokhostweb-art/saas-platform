<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\State\InstallationStateBridge;
use App\Services\CustomerInstallation\State\StateResolver;
use Tests\TestCase;

class InstallationStateBridgeTest extends TestCase
{
    private InstallationStateBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bridge = new InstallationStateBridge();
    }

    public function test_valid_overlay_always_overrides_database_state(): void
    {
        foreach (StateResolver::STATES as $overlayState) {
            foreach (StateResolver::STATES as $databaseState) {
                $installation = $this->installation($databaseState, $overlayState);

                $this->assertSame($overlayState, $this->bridge->read($installation));
            }
        }
    }

    public function test_database_state_is_used_only_when_overlay_is_null(): void
    {
        $missingOverlay = new CustomerInstallation([
            'status' => 'customer_ready',
            'metadata' => [],
        ]);
        $nullOverlay = $this->installation('customer_ready', null);

        $this->assertSame(StateResolver::CUSTOMER_READY, $this->bridge->read($missingOverlay));
        $this->assertSame(StateResolver::CUSTOMER_READY, $this->bridge->read($nullOverlay));
    }

    public function test_resolution_is_deterministic_for_the_same_input(): void
    {
        $installation = $this->installation('customer_ready', 'failed');
        $results = [];

        for ($iteration = 0; $iteration < 20; $iteration++) {
            $results[] = $this->bridge->read($installation);
        }

        $this->assertSame([StateResolver::FAILED], array_values(array_unique($results)));
        $this->assertSame(
            $this->bridge->read($installation),
            (new InstallationStateBridge())->read($this->installation('customer_ready', 'failed')),
        );
    }

    public function test_failed_overlay_wins_conflict_with_customer_ready_database_state(): void
    {
        $installation = $this->installation('CUSTOMER_READY', 'FAILED');

        for ($iteration = 0; $iteration < 20; $iteration++) {
            $this->assertSame(StateResolver::FAILED, $this->bridge->read($installation));
        }
    }

    public function test_lowercase_legacy_values_are_stable(): void
    {
        $this->assertSame(
            StateResolver::CUSTOMER_READY,
            $this->bridge->read($this->installation('failed', 'customer_ready')),
        );
        $this->assertSame(
            StateResolver::FAILED,
            $this->bridge->read($this->installation('failed', null)),
        );
    }

    public function test_null_database_state_never_crashes(): void
    {
        $this->assertSame(StateResolver::FAILED, $this->bridge->read($this->installation(null, null)));
    }

    public function test_corrupted_database_state_never_crashes(): void
    {
        $this->assertIsString($this->bridge->read($this->installation("\0corrupted", null)));
    }

    public function test_unknown_database_state_never_crashes(): void
    {
        $this->assertSame(
            StateResolver::FAILED,
            $this->bridge->read($this->installation('future_state', null)),
        );
    }

    public function test_unknown_overlay_never_crashes_or_silently_falls_back_to_database(): void
    {
        $resolved = $this->bridge->read($this->installation('customer_ready', 'future_state'));

        $this->assertSame(StateResolver::INVALID_STATE, $resolved);
    }

    public function test_invalid_state_blocks_transitions_without_propagating_an_exception(): void
    {
        $installation = $this->installation('customer_ready', 'future_state');

        $this->assertSame(StateResolver::INVALID_STATE, $this->bridge->transition($installation, 'running'));
        $this->assertSame(
            StateResolver::INVALID_STATE,
            $this->bridge->transition($this->installation('running', null), 'future_state'),
        );
    }

    private function installation(?string $databaseState, ?string $overlayState): CustomerInstallation
    {
        return new CustomerInstallation([
            'status' => $databaseState,
            'metadata' => ['installation_state' => $overlayState],
        ]);
    }
}
