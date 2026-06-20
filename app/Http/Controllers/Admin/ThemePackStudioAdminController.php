<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemePack;
use Illuminate\Http\Request;

/**
 * Admin panel for managing theme packs imported from TB-AI Studio.
 */
class ThemePackStudioAdminController extends Controller
{
    public function index()
    {
        $packs = ThemePack::whereJsonContains('tags', 'studio-import')
            ->orderByDesc('created_at')
            ->paginate(20);

        $apiKey        = config('services.studio.api_key', '');
        $apiConfigured = !empty($apiKey);

        return view('admin.studio-imports.index', compact('packs', 'apiConfigured'));
    }

    public function show(ThemePack $pack)
    {
        abort_unless(in_array('studio-import', $pack->tags ?? []), 404);
        return view('admin.studio-imports.show', compact('pack'));
    }

    public function publish(ThemePack $pack, Request $request)
    {
        abort_unless(in_array('studio-import', $pack->tags ?? []), 404);

        $data = $request->validate([
            'price'       => 'nullable|numeric|min:0',
            'is_featured' => 'nullable|boolean',
        ]);

        $pack->update(['is_public' => true, 'is_active' => true]);

        if (class_exists(\App\Models\ThemeMarketplace::class)) {
            \App\Models\ThemeMarketplace::updateOrCreate(
                ['theme_pack_uuid' => $pack->uuid],
                [
                    'name'        => $pack->name,
                    'description' => $pack->description,
                    'price'       => $data['price'] ?? 0,
                    'is_featured' => $data['is_featured'] ?? false,
                    'is_active'   => true,
                    'tags'        => $pack->tags ?? [],
                ]
            );
        }

        return redirect()->route('admin.studio-imports.index')
            ->with('success', "پک «{$pack->name}» در مارکت‌پلیس منتشر شد.");
    }

    public function destroy(ThemePack $pack)
    {
        abort_unless(in_array('studio-import', $pack->tags ?? []), 404);
        $name = $pack->name;
        $pack->delete();
        return redirect()->route('admin.studio-imports.index')
            ->with('success', "پک «{$name}» حذف شد.");
    }
}
