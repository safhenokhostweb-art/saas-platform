<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSearchConsoleConnection;
use Tests\TestCase;

/**
 * Phase 18-E — Part 2/11: encrypted-at-rest credentials + strict
 * per-site isolation — "هر Site فقط اطلاعات خودش را ببیند."
 */
class SearchConsoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_and_refresh_tokens_are_encrypted_at_rest(): void
    {
        SeoAdvancedSearchConsoleConnection::create([
            'site_id' => 1, 'access_token' => 'plain-access-token', 'refresh_token' => 'plain-refresh-token',
        ]);

        $raw = DB::table('seo_advanced_search_console_connections')->where('site_id', 1)->first();

        $this->assertStringNotContainsString('plain-access-token', $raw->access_token);
        $this->assertStringNotContainsString('plain-refresh-token', $raw->refresh_token);
    }

    public function test_tokens_decrypt_transparently_through_the_model(): void
    {
        $connection = SeoAdvancedSearchConsoleConnection::create([
            'site_id' => 1, 'access_token' => 'my-token', 'refresh_token' => 'my-refresh',
        ]);

        $this->assertSame('my-token', $connection->fresh()->access_token);
        $this->assertSame('my-refresh', $connection->fresh()->refresh_token);
    }

    public function test_tokens_are_hidden_from_array_and_json_serialization(): void
    {
        $connection = SeoAdvancedSearchConsoleConnection::create([
            'site_id' => 1, 'access_token' => 'secret', 'refresh_token' => 'secret2',
        ]);

        $array = $connection->fresh()->toArray();

        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);
    }

    public function test_each_site_has_at_most_one_connection(): void
    {
        SeoAdvancedSearchConsoleConnection::create(['site_id' => 1, 'property_url' => 'https://a.com/']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        SeoAdvancedSearchConsoleConnection::create(['site_id' => 1, 'property_url' => 'https://b.com/']);
    }

    public function test_connections_are_isolated_per_site(): void
    {
        SeoAdvancedSearchConsoleConnection::create(['site_id' => 1, 'property_url' => 'https://site-one.com/', 'access_token' => 'a']);
        SeoAdvancedSearchConsoleConnection::create(['site_id' => 2, 'property_url' => 'https://site-two.com/', 'access_token' => 'b']);

        $siteOne = SeoAdvancedSearchConsoleConnection::where('site_id', 1)->first();
        $siteTwo = SeoAdvancedSearchConsoleConnection::where('site_id', 2)->first();

        $this->assertSame('https://site-one.com/', $siteOne->property_url);
        $this->assertSame('https://site-two.com/', $siteTwo->property_url);
        $this->assertNotSame($siteOne->access_token, $siteTwo->access_token);
    }

    public function test_is_token_expired_reflects_the_stored_expiry(): void
    {
        $expired = SeoAdvancedSearchConsoleConnection::create(['site_id' => 1, 'token_expires_at' => now()->subHour()]);
        $fresh = SeoAdvancedSearchConsoleConnection::create(['site_id' => 2, 'token_expires_at' => now()->addHour()]);

        $this->assertTrue($expired->isTokenExpired());
        $this->assertFalse($fresh->isTokenExpired());
    }
}
