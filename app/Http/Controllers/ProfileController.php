<?php
namespace App\Http\Controllers;

use App\Services\Infrastructure\MediaStorage;
use App\Services\Infrastructure\StorageManager;
use Illuminate\Http\Request;
use App\Models\Profile;
use App\Models\ProfileField;
use App\Models\ProfileFieldValue;
use Illuminate\Support\Facades\Auth;
use App\Rules\IranianMobile;
use App\Rules\IranianPostalCode;

class ProfileController extends Controller
{
    public function __construct(private StorageManager $storage, private MediaStorage $media) {}

    public function show()
    {
        $user = Auth::user();

        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            ['mobile' => $user->mobile]
        );

        $fields = ProfileField::where('enabled', 1)->get();

        $mapField = $fields->where('type', 'map')->first();

        return view('complete-profile', compact('profile', 'fields', 'mapField'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $profile = $user->profile ?? Profile::firstOrCreate(
            ['user_id' => $user->id],
            ['mobile' => $user->mobile]
        );

        $fields = ProfileField::where('enabled', 1)->get();

        $rules = [];
        $attributes = [];
        foreach ($fields as $field) {
            if ($field->type === 'map') continue;
            $key = 'fields.' . $field->id;
            $attributes[$key] = $field->label;
            $rules[$key] = $field->required ? ['required'] : ['nullable'];
            if ($field->key === 'mobile') {
                $rules[$key][] = new IranianMobile;
            } elseif ($field->key === 'postal_code') {
                $rules[$key][] = new IranianPostalCode;
            } elseif ($field->key === 'email') {
                $rules[$key][] = 'email';
            } else {
                $rules[$key][] = 'string';
                $rules[$key][] = 'max:1000';
            }
        }
        $request->validate($rules, [], $attributes);

        foreach ($fields as $field) {
            $value = $request->input("fields.{$field->id}");

            ProfileFieldValue::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'profile_field_id' => $field->id,
                ],
                [
                    'value' => $value,
                ]
            );
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $profile->lat = $request->lat;
            $profile->lng = $request->lng;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'map_link') && $request->filled('map_link')) {
            $profile->map_link = $request->map_link;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'map_address') && $request->filled('map_address')) {
            $profile->map_address = $request->map_address;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'is_complete')) {
            $profile->is_complete = true;
        }
        $profile->save();

        return redirect()->route('dashboard');
    }

    public function edit()
    {
        $user = Auth::user();
        
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            ['mobile' => $user->mobile]
        );

        $fields = ProfileField::where('enabled', 1)->get();
        
        // بارگذاری مقادیر موجود
        $fieldValues = ProfileFieldValue::where('user_id', $user->id)
            ->pluck('value', 'profile_field_id')
            ->toArray();

        $mapField = $fields->where('type', 'map')->first();

        return view('profile.edit', compact('profile', 'fields', 'fieldValues', 'mapField'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $profile = $user->profile;

        if (!$profile) {
            $profile = Profile::create([
                'user_id' => $user->id,
                'mobile'  => $user->mobile,
            ]);
        }

        $fields = ProfileField::where('enabled', 1)->get();

        $rules = ['avatar' => ['nullable', 'image', 'max:2048']];
        $attributes = [];
        foreach ($fields as $field) {
            if ($field->type === 'map') continue;
            $key = 'fields.' . $field->id;
            $attributes[$key] = $field->label;
            $rules[$key] = $field->required ? ['required'] : ['nullable'];
            if ($field->key === 'mobile') {
                $rules[$key][] = new IranianMobile;
            } elseif ($field->key === 'postal_code') {
                $rules[$key][] = new IranianPostalCode;
            } elseif ($field->key === 'email') {
                $rules[$key][] = 'email';
            } else {
                $rules[$key][] = 'string';
                $rules[$key][] = 'max:1000';
            }
        }
        $request->validate($rules, [], $attributes);

        foreach ($fields as $field) {
            ProfileFieldValue::updateOrCreate(
                ['user_id' => $user->id, 'profile_field_id' => $field->id],
                ['value'   => $request->input("fields.{$field->id}")]
            );
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $profile->lat = $request->lat;
            $profile->lng = $request->lng;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'map_link')) {
            $profile->map_link = $request->map_link;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'map_address')) {
            $profile->map_address = $request->map_address;
        }
        if ($request->filled('full_name')) {
            $profile->full_name = $request->full_name;
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid() && $file->getSize() > 0) {
                try {
                    $ext      = $file->guessExtension() ?: 'jpg';
                    $filename = 'user_' . $user->id . '_' . time() . '.' . $ext;
                    $diskPath = $this->storage->storeUploadedFile($file, 'avatars', $this->media->diskNameFor('uploads'), $filename);
                    $profile->avatar = 'uploads/' . $diskPath;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Profile avatar upload: ' . $e->getMessage());
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'is_complete')) {
            $profile->is_complete = true;
        }
        $profile->save();

        return redirect()->route('profile.edit')
            ->with('success', 'پروفایل با موفقیت بروزرسانی شد');
    }
}
