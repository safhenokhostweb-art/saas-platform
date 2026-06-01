<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeProfile;
use App\Services\ThemeEngine\ThemeProfileManager;
use App\Services\ThemeEngine\ThemeTokenRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Enterprise Theme Manager — CRUD, activation, cloning, preview, reset.
 * All write operations require admin authentication (enforced by route middleware).
 */
class ThemeManagerController extends Controller
{
    public function __construct(
        private ThemeProfileManager $manager,
        private ThemeTokenRenderer  $renderer,
    ) {}

    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $profiles = ThemeProfile::orderByDesc('is_system')
            ->orderBy('name')
            ->get();
        $active = ThemeProfile::activePlatform();

        // Load sites and their current theme assignments for the "Assign" modal
        $sites = \App\Models\Site::orderBy('title')->get(['id', 'title', 'domain']);
        $siteAssignments = \App\Models\TenantThemeOverride::with('themeProfile')
            ->get()
            ->keyBy('site_id');

        return view('admin.theme-manager.index', compact('profiles', 'active', 'sites', 'siteAssignments'));
    }

    // ── Create / Store ───────────────────────────────────────────────────────

    public function create()
    {
        return view('admin.theme-manager.edit', [
            'profile' => null,
            'isNew'   => true,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                       => 'required|string|max:80',
            'description'                => 'nullable|string|max:300',
            'colors.primary.500'         => 'required|string|max:30',
            'colors.accent.base'         => 'required|string|max:30',
            'colors.secondary.base'      => 'required|string|max:30',
            'colors.success.base'        => 'nullable|string|max:30',
            'colors.warning.base'        => 'nullable|string|max:30',
            'colors.danger.base'         => 'nullable|string|max:30',
            'colors.info.base'           => 'nullable|string|max:30',
            'surfaces.bg'                => 'nullable|string|max:30',
            'surfaces.surface'           => 'nullable|string|max:30',
            'typography.text_primary'    => 'nullable|string|max:30',
            'typography.text_muted'      => 'nullable|string|max:30',
            'typography.link'            => 'nullable|string|max:30',
            'borders.border'             => 'nullable|string|max:30',
            'sidebar.bg'                 => 'nullable|string|max:30',
            'sidebar.bg_bottom'          => 'nullable|string|max:30',
            'sidebar.text'               => 'nullable|string|max:30',
            'sidebar.accent'             => 'nullable|string|max:30',
            'header.bg'                  => 'nullable|string|max:30',
            'header.text'                => 'nullable|string|max:30',
            'header.link_active'         => 'nullable|string|max:30',
        ]);

        $data['colors']     = $this->buildColorGroup($request);
        $data['surfaces']   = $request->input('surfaces', []);
        $data['typography'] = $request->input('typography', []);
        $data['borders']    = $request->input('borders', []);
        $data['sidebar']    = $request->input('sidebar', []);
        $data['header']     = $request->input('header', []);

        $profile = $this->manager->create($data);

        return redirect()
            ->route('admin.theme-manager.index')
            ->with('success', "تم «{$profile->name}» ساخته شد.");
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(ThemeProfile $themeProfile)
    {
        return view('admin.theme-manager.edit', [
            'profile' => $themeProfile,
            'isNew'   => false,
        ]);
    }

    public function update(Request $request, ThemeProfile $themeProfile)
    {
        $data = [
            'name'        => $request->input('name', $themeProfile->name),
            'description' => $request->input('description', $themeProfile->description),
            'colors'      => $this->buildColorGroup($request) ?: $themeProfile->colors,
            'surfaces'    => $request->input('surfaces', $themeProfile->surfaces),
            'typography'  => $request->input('typography', $themeProfile->typography),
            'borders'     => $request->input('borders', $themeProfile->borders),
            'sidebar'     => $request->input('sidebar', $themeProfile->sidebar),
            'header'      => $request->input('header', $themeProfile->header),
        ];

        $this->manager->update($themeProfile, $data);

        return redirect()
            ->route('admin.theme-manager.index')
            ->with('success', "تم «{$themeProfile->name}» به‌روزرسانی شد.");
    }

    // ── Activation ───────────────────────────────────────────────────────────

    public function activate(ThemeProfile $themeProfile)
    {
        $this->manager->activatePlatform($themeProfile);

        return redirect()
            ->route('admin.theme-manager.index')
            ->with('success', "تم «{$themeProfile->name}» فعال شد.");
    }

    // ── Clone ────────────────────────────────────────────────────────────────

    public function clone(Request $request, ThemeProfile $themeProfile)
    {
        $newName = $request->input('name', $themeProfile->name . ' — کپی');
        $clone   = $this->manager->clone($themeProfile, $newName);

        return redirect()
            ->route('admin.theme-manager.edit', $clone)
            ->with('success', "تم کلون شد: «{$clone->name}»");
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function destroy(ThemeProfile $themeProfile)
    {
        $name = $themeProfile->name;
        $this->manager->delete($themeProfile);

        return redirect()
            ->route('admin.theme-manager.index')
            ->with('success', "تم «{$name}» حذف شد.");
    }

    // ── Assign to Sites ──────────────────────────────────────────────────────

    public function assignToSites(\Illuminate\Http\Request $request, ThemeProfile $themeProfile)
    {
        $data    = $request->validate(['site_ids' => 'nullable|array', 'site_ids.*' => 'integer|exists:sites,id']);
        $siteIds = $data['site_ids'] ?? [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($themeProfile, $siteIds) {
            // Remove assignments that were deselected
            \App\Models\TenantThemeOverride::where('theme_profile_id', $themeProfile->id)
                ->whereNotIn('site_id', $siteIds)
                ->delete();
            // Add or update selected sites
            foreach ($siteIds as $siteId) {
                \App\Models\TenantThemeOverride::setForSite($siteId, $themeProfile->id);
                $this->manager->clearTenantCache($siteId);
            }
        });

        return redirect()
            ->route('admin.theme-manager.index')
            ->with('success', "تم «{$themeProfile->name}» به " . count($siteIds) . " سایت اختصاص یافت.");
    }

    // ── Reset to Default ─────────────────────────────────────────────────────

    public function resetDefault()
    {
        $this->manager->resetToDefault();

        return redirect()
            ->route('admin.theme-manager.index')
            ->with('success', 'تم پلتفرم به Enterprise Blue بازنشانی شد.');
    }

    // ── Preview (AJAX) ────────────────────────────────────────────────────────

    public function preview(Request $request, ThemeProfile $themeProfile): JsonResponse
    {
        $key = 'user_' . auth()->id() . '_' . session()->getId();
        $this->manager->storePreview($key, $themeProfile);

        return response()->json([
            'preview_key' => $key,
            'tokens'      => $themeProfile->toCssVars(),
            'css_block'   => $this->renderer->renderCssBlock($themeProfile),
        ]);
    }

    /**
     * Live preview tokens without storing anything — pure AJAX for client-side apply.
     */
    public function previewTokens(ThemeProfile $themeProfile): JsonResponse
    {
        return response()->json([
            'tokens' => $themeProfile->toCssVars(),
            'name'   => $themeProfile->name,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildColorGroup(Request $request): array
    {
        $raw = $request->input('colors', []);
        if (empty($raw)) {
            return [];
        }
        // Derive primary shades from a single hex if shades not provided
        if (!empty($raw['primary']['500']) && empty($raw['primary']['50'])) {
            $raw['primary'] = $this->deriveShades($raw['primary']['500']);
        }
        return $raw;
    }

    /**
     * Derive a 10-shade primary palette from a single hex color using HSL offsets.
     * These are reasonable defaults — users can override individual shades via the edit form.
     */
    private function deriveShades(string $hex): array
    {
        // Predefined HSL lightness offsets relative to 500 (the base)
        $shades = [
            '50'  => 97, '100' => 94, '200' => 88,
            '300' => 79, '400' => 67, '500' => 0,
            '600' => -10, '700' => -20, '800' => -30, '900' => -38,
        ];

        [$h, $s, $l] = $this->hexToHsl($hex);
        $result = [];
        foreach ($shades as $key => $offset) {
            $newL = $key === '500' ? $l : max(5, min(97, $l + $offset));
            $result[$key] = $this->hslToHex($h, max(10, $s - abs($offset) * 0.3), $newL);
        }
        $result['500'] = $hex; // Always preserve exact input
        return $result;
    }

    private function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $s = 0;
        $h = 0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            $h = match ($max) {
                $r      => (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6,
                $g      => (($b - $r) / $d + 2) / 6,
                default => (($r - $g) / $d + 4) / 6,
            };
        }
        return [round($h * 360), round($s * 100), round($l * 100)];
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;
        $a = $s * min($l, 1 - $l);
        $f = function (int $n) use ($h, $l, $a): int {
            $k = fmod($n + $h / 30, 12);
            return (int) round(255 * ($l - $a * max(-1, min($k - 3, 9 - $k, 1))));
        };
        return sprintf('#%02x%02x%02x', $f(0), $f(8), $f(4));
    }
}
