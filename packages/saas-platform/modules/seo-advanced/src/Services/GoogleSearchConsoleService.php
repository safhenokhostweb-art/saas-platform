<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use Illuminate\Support\Facades\Http;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SearchConsolePerformanceProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSearchConsoleConnection;

/**
 * Phase 18-E — Part 2: real Google Search Console integration, built
 * directly against Google's documented REST endpoints via Laravel's Http
 * facade — deliberately NOT using the google/apiclient or
 * laravel/socialite packages, since neither is already a dependency of
 * this platform and adding one would be an avoidable Core-adjacent
 * change (composer.json) for what is, functionally, three plain HTTP
 * calls (token exchange, list sites, query search analytics).
 *
 * NOTE: written correctly against Google's documented API shape but has
 * not been exercised against a live Google Cloud project/OAuth consent
 * screen in this environment (no real credentials available) — flagged
 * explicitly in the Phase 18-E final report as an integration-level risk
 * to verify before relying on it with a real customer's Google account.
 */
class GoogleSearchConsoleService implements SearchConsolePerformanceProviderInterface
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const API_BASE = 'https://searchconsole.googleapis.com/webmasters/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function buildAuthorizationUrl(int $siteId, string $redirectUri): string
    {
        $params = [
            'client_id' => config('seo-advanced.search_console.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => (string) $siteId,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function handleCallback(int $siteId, string $code, string $redirectUri): SeoAdvancedSearchConsoleConnection
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('seo-advanced.search_console.client_id'),
            'client_secret' => config('seo-advanced.search_console.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        return SeoAdvancedSearchConsoleConnection::updateOrCreate(
            ['site_id' => $siteId],
            [
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($response['expires_in'] ?? 3600),
                'connected_at' => now(),
            ]
        );
    }

    /** @return array<int, array{url: string, permission_level: string}> */
    public function listProperties(int $siteId): array
    {
        $token = $this->freshAccessToken($siteId);
        if (!$token) {
            return [];
        }

        $response = Http::withToken($token)->get(self::API_BASE . '/sites')->throw()->json();

        return collect($response['siteEntry'] ?? [])
            ->map(fn ($entry) => ['url' => $entry['siteUrl'], 'permission_level' => $entry['permissionLevel'] ?? ''])
            ->all();
    }

    public function selectProperty(int $siteId, string $propertyUrl): void
    {
        SeoAdvancedSearchConsoleConnection::where('site_id', $siteId)->update(['property_url' => $propertyUrl]);
    }

    public function disconnect(int $siteId): void
    {
        SeoAdvancedSearchConsoleConnection::where('site_id', $siteId)->delete();
    }

    public function isConnected(int $siteId): bool
    {
        return SeoAdvancedSearchConsoleConnection::where('site_id', $siteId)
            ->whereNotNull('property_url')
            ->exists();
    }

    public function getPerformance(int $siteId, string $startDate, string $endDate): array
    {
        $empty = ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'average_position' => 0.0, 'top_queries' => [], 'top_pages' => []];

        $connection = SeoAdvancedSearchConsoleConnection::where('site_id', $siteId)->first();
        $token = $this->freshAccessToken($siteId);
        if (!$connection || !$connection->property_url || !$token) {
            return $empty;
        }

        $summary = $this->queryAnalytics($token, $connection->property_url, $startDate, $endDate, []);
        $byQuery = $this->queryAnalytics($token, $connection->property_url, $startDate, $endDate, ['query'], 10);
        $byPage = $this->queryAnalytics($token, $connection->property_url, $startDate, $endDate, ['page'], 10);

        $totals = $summary[0] ?? ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];

        return [
            'clicks' => (int) $totals['clicks'],
            'impressions' => (int) $totals['impressions'],
            'ctr' => round((float) $totals['ctr'] * 100, 2),
            'average_position' => round((float) $totals['position'], 1),
            'top_queries' => array_map(fn ($r) => [
                'query' => $r['keys'][0] ?? '', 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'],
                'ctr' => round((float) $r['ctr'] * 100, 2), 'position' => round((float) $r['position'], 1),
            ], $byQuery),
            'top_pages' => array_map(fn ($r) => [
                'page' => $r['keys'][0] ?? '', 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'],
                'ctr' => round((float) $r['ctr'] * 100, 2), 'position' => round((float) $r['position'], 1),
            ], $byPage),
        ];
    }

    /** @return array<int, array{keys?: array, clicks: float, impressions: float, ctr: float, position: float}> */
    private function queryAnalytics(string $token, string $propertyUrl, string $startDate, string $endDate, array $dimensions, int $rowLimit = 1): array
    {
        $response = Http::withToken($token)
            ->post(self::API_BASE . '/sites/' . rawurlencode($propertyUrl) . '/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'rowLimit' => $rowLimit,
            ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json('rows', []);
    }

    /** Refreshes the access token via refresh_token when expired; persists the new token. Returns null when there's no usable token at all. */
    private function freshAccessToken(int $siteId): ?string
    {
        $connection = SeoAdvancedSearchConsoleConnection::where('site_id', $siteId)->first();
        if (!$connection || !$connection->access_token) {
            return null;
        }

        if (!$connection->isTokenExpired()) {
            return $connection->access_token;
        }

        if (!$connection->refresh_token) {
            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'refresh_token' => $connection->refresh_token,
            'client_id' => config('seo-advanced.search_console.client_id'),
            'client_secret' => config('seo-advanced.search_console.client_secret'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $connection->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        return $data['access_token'];
    }
}
