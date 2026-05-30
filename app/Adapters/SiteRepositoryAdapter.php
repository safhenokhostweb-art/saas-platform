<?php

namespace App\Adapters;

use App\Models\Site;
use Illuminate\Support\Collection;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;

class SiteRepositoryAdapter implements SiteRepositoryInterface
{
    public function allForDisplay(): Collection
    {
        try {
            return Site::orderBy('title')->get(['id', 'title', 'domain', 'is_active']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function allActive(): Collection
    {
        try {
            return Site::where('is_active', true)->orderBy('title')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function allIds(): Collection
    {
        try {
            return Site::pluck('id');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function find(int $id): ?SiteModelInterface
    {
        try {
            return Site::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function count(): int
    {
        try {
            return Site::count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
