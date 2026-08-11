<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Infrastructure\MediaStorage;
use App\Services\Infrastructure\StorageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminProfileController extends Controller
{
    public function __construct(private StorageManager $storage, private MediaStorage $media) {}

    public function index()
    {
        $user = auth()->user()->load('profile');
        return view('admin.profile.index', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'password'  => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar'    => ['nullable', 'image', 'max:2048'],
        ]);

        // User model update
        $userUpdate = [];
        if (!empty($request->input('password'))) {
            $userUpdate['password'] = Hash::make($request->input('password'));
        }
        if (!empty($userUpdate)) {
            $user->update($userUpdate);
        }

        // Avatar upload
        $profileData = [];
        if (!empty($request->input('full_name'))) {
            $profileData['full_name'] = $request->input('full_name');
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid() && $file->getSize() > 0) {
                try {
                    $ext      = $file->guessExtension() ?: 'jpg';
                    $filename = 'admin_' . $user->id . '_' . time() . '.' . $ext;
                    $diskPath = $this->storage->storeUploadedFile($file, 'admin-avatars', $this->media->diskNameFor('uploads'), $filename);
                    $profileData['avatar'] = 'uploads/' . $diskPath;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Admin avatar upload: ' . $e->getMessage());
                }
            }
        }

        if (!empty($profileData)) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);
        }

        $avatarUrl = $user->fresh('profile')->profile?->avatar_url;

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'    => true,
                'message'    => 'پروفایل با موفقیت به‌روزرسانی شد.',
                'avatar_url' => $avatarUrl,
            ]);
        }

        return back()->with('success', 'پروفایل با موفقیت به‌روزرسانی شد.');
    }
}
