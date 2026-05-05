<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformNotificationTemplate;
use Illuminate\Http\Request;

/**
 * مدیریت قالب‌های اعلان خوش‌آمدگویی — ارسال خودکار به سایت‌های جدید.
 */
class PlatformNotificationTemplateController extends Controller
{
    public function index()
    {
        $templates = PlatformNotificationTemplate::orderBy('order')->orderBy('id')->get();
        return view('admin.notification-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('admin.notification-templates.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'type' => 'required|in:info,success,warning,danger',
            'link' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'image_path' => 'nullable|string|max:500',
            'position' => 'required|in:top,left,right',
            'image_position' => 'required|in:background,left,right',
            'audience' => 'required|in:admin',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['order'] = PlatformNotificationTemplate::max('order') + 1;
        PlatformNotificationTemplate::create($data);
        return redirect()->route('admin.notification-templates.index')
            ->with('success', 'قالب اعلان خوش‌آمدگویی اضافه شد.');
    }

    public function edit(PlatformNotificationTemplate $template)
    {
        return view('admin.notification-templates.edit', compact('template'));
    }

    public function update(Request $request, PlatformNotificationTemplate $template)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'type' => 'required|in:info,success,warning,danger',
            'link' => 'nullable|string|max:500',
            'link_text' => 'nullable|string|max:100',
            'image_path' => 'nullable|string|max:500',
            'position' => 'required|in:top,left,right',
            'image_position' => 'required|in:background,left,right',
            'audience' => 'required|in:admin',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $template->update($data);
        return redirect()->route('admin.notification-templates.index')
            ->with('success', 'قالب به‌روزرسانی شد.');
    }

    public function destroy(PlatformNotificationTemplate $template)
    {
        $template->delete();
        return redirect()->route('admin.notification-templates.index')
            ->with('success', 'قالب حذف شد.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|max:5120|mimes:jpeg,jpg,png,gif,webp',
        ]);
        $path = $request->file('image')->store('notifications', 'public');
        return response()->json(['path' => $path, 'url' => '/storage/' . $path]);
    }
}
