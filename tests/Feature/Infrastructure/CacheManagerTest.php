<?php

namespace Tests\Feature\Infrastructure;

use App\Services\Infrastructure\CacheManager;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheManagerTest extends TestCase
{
    private CacheManager $cm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cm = app(CacheManager::class);
        // Clear all named stores before each test
        foreach ($this->cm->storeNames() as $store) {
            Cache::store($store)->flush();
        }
    }

    // ── key() ────────────────────────────────────────────────────────────────

    public function test_key_without_site_id_returns_plain_name(): void
    {
        $this->assertSame('stats', $this->cm->key('stats'));
    }

    public function test_key_with_site_id_returns_scoped_key(): void
    {
        $this->assertSame('site_42:stats', $this->cm->key('stats', 42));
    }

    public function test_key_with_site_id_zero_produces_scoped_key(): void
    {
        // siteId=0 is not null — key() is strict. Callers are responsible for
        // not passing 0; database site IDs start from 1.
        $this->assertSame('site_0:stats', $this->cm->key('stats', 0));
    }

    // ── store() ──────────────────────────────────────────────────────────────

    public function test_store_returns_cache_repository(): void
    {
        $store = $this->cm->store('users');
        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Repository::class, $store);
    }

    // ── remember() / get() ───────────────────────────────────────────────────

    public function test_remember_stores_and_retrieves_value(): void
    {
        $result = $this->cm->remember('users', 'test_key', 60, fn () => 'hello');
        $this->assertSame('hello', $result);

        // Second call must return cached value, not call the closure again
        $result2 = $this->cm->remember('users', 'test_key', 60, fn () => 'SHOULD_NOT_BE_RETURNED');
        $this->assertSame('hello', $result2);
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->cm->get('users', 'non_existent_key'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertSame('default_val', $this->cm->get('users', 'non_existent_key', 'default_val'));
    }

    // ── put() / forget() ─────────────────────────────────────────────────────

    public function test_put_stores_value_and_get_retrieves_it(): void
    {
        $this->cm->put('customers', 'customer_stats', ['total' => 5], 60);
        $this->assertSame(['total' => 5], $this->cm->get('customers', 'customer_stats'));
    }

    public function test_forget_removes_key(): void
    {
        $this->cm->put('modules', 'module_features_forms', ['feature_a'], 60);
        $this->assertNotNull($this->cm->get('modules', 'module_features_forms'));

        $this->cm->forget('modules', 'module_features_forms');
        $this->assertNull($this->cm->get('modules', 'module_features_forms'));
    }

    // ── flushStore() isolation ───────────────────────────────────────────────

    public function test_flush_store_clears_only_target_store(): void
    {
        $this->cm->put('users', 'user_key', 'user_value', 60);
        $this->cm->put('customers', 'customer_key', 'customer_value', 60);

        $this->cm->flushStore('users');

        // Users store should be empty
        $this->assertNull($this->cm->get('users', 'user_key'), 'users store should be cleared');
        // Customers store must be untouched
        $this->assertSame('customer_value', $this->cm->get('customers', 'customer_key'), 'customers store must survive users flush');
    }

    public function test_flush_store_does_not_affect_themes_store(): void
    {
        $this->cm->put('themes', 'theme_key', 'theme_value', 60);
        $this->cm->put('tickets', 'ticket_key', 'ticket_value', 60);

        $this->cm->flushStore('tickets');

        $this->assertNull($this->cm->get('tickets', 'ticket_key'), 'tickets store should be cleared');
        $this->assertSame('theme_value', $this->cm->get('themes', 'theme_key'), 'themes store must survive tickets flush');
    }

    // ── tenant isolation ─────────────────────────────────────────────────────

    public function test_tenant_keys_are_isolated_from_platform_keys(): void
    {
        $platformKey = $this->cm->key('stats');
        $tenant42Key = $this->cm->key('stats', 42);
        $tenant99Key = $this->cm->key('stats', 99);

        $this->cm->put('customers', $platformKey, 'platform_stats', 60);
        $this->cm->put('customers', $tenant42Key, 'tenant42_stats', 60);
        $this->cm->put('customers', $tenant99Key, 'tenant99_stats', 60);

        $this->assertSame('platform_stats', $this->cm->get('customers', $platformKey));
        $this->assertSame('tenant42_stats', $this->cm->get('customers', $tenant42Key));
        $this->assertSame('tenant99_stats', $this->cm->get('customers', $tenant99Key));
    }

    // ── isKnownStore() ───────────────────────────────────────────────────────

    public function test_known_stores_are_recognised(): void
    {
        $this->assertTrue($this->cm->isKnownStore('users'));
        $this->assertTrue($this->cm->isKnownStore('customers'));
        $this->assertTrue($this->cm->isKnownStore('themes'));
        $this->assertTrue($this->cm->isKnownStore('tickets'));
        $this->assertTrue($this->cm->isKnownStore('modules'));
        $this->assertTrue($this->cm->isKnownStore('rate_limit'));
    }

    public function test_unknown_store_name_is_not_recognised(): void
    {
        $this->assertFalse($this->cm->isKnownStore('nonexistent'));
        $this->assertFalse($this->cm->isKnownStore('default'));
    }

    // ── storeNames() ─────────────────────────────────────────────────────────

    public function test_store_names_returns_all_known_stores(): void
    {
        $names = $this->cm->storeNames();
        $this->assertContains('users', $names);
        $this->assertContains('customers', $names);
        $this->assertContains('themes', $names);
        $this->assertContains('tickets', $names);
        $this->assertContains('modules', $names);
        $this->assertContains('rate_limit', $names);
        $this->assertCount(6, $names);
    }
}
