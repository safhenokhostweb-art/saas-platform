<?php

namespace Tests\Unit\Architecture;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\Interaction\InteractionDispatcher;
use SaaSPlatform\ThemeRuntime\Interaction\InteractionEvent;
use SaaSPlatform\ThemeRuntime\Interaction\InteractionPolicy;
use SaaSPlatform\ThemeRuntime\InteractionBlockTypes;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;

/** Phase 3C / Milestone 3 — lightweight interaction contracts. */
class Phase3CInteractionContractsTest extends TestCase
{
    private const TYPES = ['interactive_form', 'action_button'];

    protected function setUp(): void
    {
        parent::setUp();
        BlockRegistry::resetHostTypes();
        InteractionBlockTypes::register();
    }

    protected function tearDown(): void
    {
        BlockRegistry::resetHostTypes();
        parent::tearDown();
    }

    /** @test */
    public function interactive_widgets_register_additively_with_normalized_interaction_config(): void
    {
        foreach (self::TYPES as $type) {
            $definition = BlockRegistry::all()[$type] ?? null;
            $this->assertIsArray($definition);
            $this->assertTrue(BlockRegistry::isHostRegisteredType($type));
            $this->assertFalse(BlockRegistry::isCanonicalType($type));
            $this->assertSame($definition, WidgetConfig::normalize($type, $definition));
            $this->assertSame('interaction_config', $definition['interaction_config']['property']);
        }

        $form = BlockRegistry::all()['interactive_form']['interaction_config'];
        $this->assertSame(['submit'], $form['events']);
        $this->assertSame(['call_api'], $form['actions']);

        $button = BlockRegistry::all()['action_button']['interaction_config'];
        $this->assertSame(['click'], $button['events']);
        $this->assertSame(['navigate', 'call_api', 'scroll_to'], $button['actions']);
    }

    /** @test */
    public function interaction_inspector_schema_uses_existing_primitive_controls(): void
    {
        foreach (self::TYPES as $type) {
            $schema = BlockRegistry::all()[$type]['schema'];
            InspectorFieldRegistry::normalizeDefinition(['schema' => $schema]);
            foreach ($schema as $field) {
                $this->assertContains($field['type'], [
                    'string', 'number', 'boolean', 'select', 'color', 'textarea', 'link',
                    'icon', // Phase 0.6 (ADR-065): the shared icon picker
                ]);
            }
        }

        $button = BlockRegistry::all()['action_button']['schema'];
        $this->assertSame('select', $button['action_type']['type']);
        $this->assertSame(['link', 'api_call', 'scroll_to'], array_keys($button['action_type']['options']));
        $this->assertSame('string', $button['target_value']['type']);
    }

    /** @test */
    public function interaction_event_is_immutable_normalized_and_round_trips(): void
    {
        $event = InteractionEvent::fromArray([
            'event_type' => 'submit',
            'target_id' => 'lead-form-1',
            'payload' => [
                'target' => 'https://api.example.test/leads',
                'values' => ['email' => 'person@example.test', 'consent' => true],
            ],
            'action_trigger' => 'call_api',
        ]);

        $this->assertSame('submit', $event->eventType);
        $this->assertSame('lead-form-1', $event->targetId);
        $this->assertSame('call_api', $event->actionTrigger);
        $this->assertSame('person@example.test', $event->payload['values']['email']);
        $this->assertSame($event->toArray(), InteractionEvent::fromArray($event->toArray())->toArray());

        foreach ([
            ['event_type' => 'eval', 'target_id' => 'safe', 'payload' => [], 'action_trigger' => 'navigate'],
            ['event_type' => 'click', 'target_id' => '../unsafe', 'payload' => [], 'action_trigger' => 'navigate'],
            ['event_type' => 'click', 'target_id' => 'safe', 'payload' => [], 'action_trigger' => 'execute_script'],
            ['event_type' => 'click', 'target_id' => 'safe', 'payload' => ['__proto__' => 'x'], 'action_trigger' => 'navigate'],
        ] as $invalid) {
            $this->expectInvalidEvent($invalid);
        }
    }

    /** @test */
    public function policy_blocks_unauthorized_navigation_and_api_targets(): void
    {
        $policy = new InteractionPolicy(
            apiHosts: ['api.example.test'],
            interactionHosts: ['www.example.test'],
        );

        $this->assertTrue($policy->allows($this->event('navigate', '/catalog')));
        $this->assertTrue($policy->allows($this->event('navigate', 'https://www.example.test/catalog')));
        $this->assertTrue($policy->allows($this->event('call_api', 'https://api.example.test/leads')));
        $this->assertTrue($policy->allows($this->event('scroll_to', '#pricing')));

        foreach ([
            $this->event('navigate', 'https://blocked.example.test/phish'),
            $this->event('navigate', 'javascript:alert(1)'),
            $this->event('call_api', 'https://blocked.example.test/collect'),
            $this->event('call_api', 'http://api.example.test/insecure'),
            $this->event('scroll_to', '#../../secrets'),
        ] as $blocked) {
            $this->assertFalse($policy->allows($blocked));
        }
    }

    /** @test */
    public function dispatcher_routes_authorized_events_and_intercepts_blocked_events(): void
    {
        $calls = [];
        $dispatcher = new InteractionDispatcher(
            new InteractionPolicy(apiHosts: ['api.example.test']),
            [
                'call_api' => static function (InteractionEvent $event) use (&$calls): array {
                    $calls[] = $event->targetId;
                    return ['accepted' => true];
                },
            ],
        );

        $handled = $dispatcher->dispatch($this->event('call_api', 'https://api.example.test/leads'));
        $this->assertSame('handled', $handled->status);
        $this->assertSame(['accepted' => true], $handled->result);
        $this->assertSame(['widget-1'], $calls);

        $blocked = $dispatcher->dispatch($this->event('call_api', 'https://blocked.example.test/leads'));
        $this->assertSame('blocked', $blocked->status);
        $this->assertSame(['widget-1'], $calls, 'A blocked event must never reach its handler.');
        $this->assertStringNotContainsString('blocked.example.test', $blocked->reason);
    }

    /** @test */
    public function renderers_emit_only_sanitized_intentions_without_executable_business_logic(): void
    {
        $renderer = new BlockRenderer;
        $renderer->setContext([
            'interaction_policy' => new InteractionPolicy(apiHosts: ['api.example.test']),
        ]);

        $form = $renderer->renderBlock([
            'id' => 'lead-form',
            'type' => 'interactive_form',
            'props' => [
                'title' => 'درخواست مشاوره',
                'endpoint_url' => 'https://api.example.test/leads',
                'submit_label' => 'ارسال',
            ],
        ]);
        $this->assertStringContainsString('<form', $form);
        $this->assertStringContainsString('data-tb-interaction=', $form);
        $this->assertStringContainsString('&quot;event_type&quot;:&quot;submit&quot;', $form);
        $this->assertStringContainsString('&quot;action_trigger&quot;:&quot;call_api&quot;', $form);
        $this->assertStringNotContainsString('fetch(', $form);

        $button = $renderer->renderBlock([
            'id' => 'pricing-button',
            'type' => 'action_button',
            'props' => ['label' => 'قیمت‌ها', 'action_type' => 'scroll_to', 'target_value' => '#pricing'],
        ]);
        $this->assertStringContainsString('<button', $button);
        $this->assertStringContainsString('&quot;action_trigger&quot;:&quot;scroll_to&quot;', $button);

        $blocked = $renderer->renderBlock([
            'id' => 'unsafe-button',
            'type' => 'action_button',
            'props' => ['label' => 'Unsafe', 'action_type' => 'link', 'target_value' => 'https://blocked.example.test'],
        ]);
        $this->assertStringContainsString('data-tb-interaction-blocked="1"', $blocked);
        $this->assertStringNotContainsString('blocked.example.test', $blocked);
    }

    /** @test */
    public function browser_dispatcher_contract_uses_delegated_events_and_revalidates_targets(): void
    {
        $runtime = file_get_contents(
            dirname(__DIR__, 3).'/packages/saas-platform/modules/theme-runtime/resources/views/interaction/runtime.blade.php'
        );
        $this->assertNotFalse($runtime);
        $this->assertStringContainsString('window.ThemeInteractionDispatcher', $runtime);
        $this->assertStringContainsString("document.addEventListener('click'", $runtime);
        $this->assertStringContainsString("document.addEventListener('submit'", $runtime);
        $this->assertStringContainsString("new CustomEvent('theme:interaction'", $runtime);
        $this->assertStringContainsString("redirect: 'error'", $runtime);
        $this->assertStringContainsString('credentials:', $runtime);
        $this->assertStringNotContainsString('eval(', $runtime);
        $this->assertStringNotContainsString('new Function(', $runtime);
    }

    /**
     * Phase 3C/M3 closing gap — client-side host allowlist: the runtime must
     * consult an injected host allowlist (mirror of the server's
     * InteractionPolicy) before executing external navigate/call_api targets,
     * and warn on every block. Contract-level: the real behavior is proven
     * in-browser during the audit.
     */
    /** @test */
    public function browser_runtime_enforces_a_client_side_host_allowlist_for_external_targets(): void
    {
        $runtime = file_get_contents(
            dirname(__DIR__, 3).'/packages/saas-platform/modules/theme-runtime/resources/views/interaction/runtime.blade.php'
        );
        $this->assertStringContainsString('window.ThemeInteractionHosts', $runtime);
        $this->assertStringContainsString('isAllowlistedHost', $runtime);
        $this->assertStringContainsString('interaction_hosts allowlist', $runtime);
        $this->assertStringContainsString('interaction_api_hosts allowlist', $runtime);
        $this->assertStringContainsString('console.warn(', $runtime);
    }

    /**
     * Phase 3C/M3 closing gap — the server-side gateway: toggle_element and
     * open_modal are authorized (and only authorized) server-side; navigate
     * and call_api require an explicit host handler; malformed payloads are
     * rejected without throwing.
     */
    /** @test */
    public function gateway_authorizes_ui_actions_and_never_invents_side_effects(): void
    {
        $gateway = new \SaaSPlatform\ThemeRuntime\Interaction\InteractionGateway(
            new InteractionPolicy(apiHosts: ['api.example.test'])
        );

        $toggle = $gateway->handle([
            'event_type' => 'click',
            'target_id' => 'panel-1',
            'payload' => ['target' => '#panel-1'],
            'action_trigger' => 'toggle_element',
        ]);
        $this->assertSame('handled', $toggle->status);
        $this->assertTrue($toggle->result['may_execute']);

        $modal = $gateway->handle([
            'event_type' => 'click',
            'target_id' => 'dialog-1',
            'payload' => ['target' => 'dialog-1'],
            'action_trigger' => 'open_modal',
        ]);
        $this->assertSame('handled', $modal->status);
        $this->assertTrue($modal->result['may_execute']);

        // A violating target is denied before any handler runs.
        $unsafe = $gateway->handle([
            'event_type' => 'click',
            'target_id' => 'widget-1',
            'payload' => ['target' => "#bad\" onload='x'"],
            'action_trigger' => 'open_modal',
        ]);
        $this->assertSame('blocked', $unsafe->status);

        // Side-effect actions need an explicit host handler.
        $nav = $gateway->handle([
            'event_type' => 'click',
            'target_id' => 'widget-1',
            'payload' => ['target' => '/pricing'],
            'action_trigger' => 'navigate',
        ]);
        $this->assertSame('unhandled', $nav->status);

        // Registered host handlers run only after the policy passes.
        $calls = [];
        $gateway->withHandler('call_api', static function (InteractionEvent $event) use (&$calls): array {
            $calls[] = $event->targetId;
            return ['sent' => true];
        });
        $api = $gateway->handle([
            'event_type' => 'submit',
            'target_id' => 'lead-form',
            'payload' => ['target' => 'https://api.example.test/leads'],
            'action_trigger' => 'call_api',
        ]);
        $this->assertSame('handled', $api->status);
        $this->assertSame(['sent' => true], $api->result);

        $blockedApi = $gateway->handle([
            'event_type' => 'submit',
            'target_id' => 'lead-form',
            'payload' => ['target' => 'https://blocked.example.test/leads'],
            'action_trigger' => 'call_api',
        ]);
        $this->assertSame('blocked', $blockedApi->status);
        $this->assertSame(['lead-form'], $calls, 'A blocked event must never reach its handler.');

        // Malformed payloads are rejected, never thrown.
        $bad = $gateway->handle(['event_type' => 'eval']);
        $this->assertSame('rejected', $bad->status);
    }

    private function event(string $action, string $target): InteractionEvent
    {
        return InteractionEvent::fromArray([
            'event_type' => $action === 'call_api' ? 'submit' : 'click',
            'target_id' => 'widget-1',
            'payload' => ['target' => $target],
            'action_trigger' => $action,
        ]);
    }

    private function expectInvalidEvent(array $value): void
    {
        try {
            InteractionEvent::fromArray($value);
            $this->fail('Invalid interaction event was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
