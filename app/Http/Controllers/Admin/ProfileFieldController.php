<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileField;
use Illuminate\Http\Request;

class ProfileFieldController extends Controller
{
    public function index()
    {
        $fields = ProfileField::orderBy('position')->get();
        return view('admin.profile-fields.index', compact('fields'));
    }

    public function create()
    {
        return view('admin.profile-fields.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
            'key'   => 'required|string|max:255|unique:profile_fields,key',
            'type'  => 'required|string|in:text,textarea,number,map',
        ]);

        ProfileField::create([
            'label'    => $request->label,
            'key'      => $request->key,
            'type'     => $request->type,
            'required' => $request->boolean('required'),
            'enabled'  => $request->boolean('enabled'),
            'position' => (ProfileField::max('position') ?? 0) + 1,
        ]);

        return redirect()->route('admin.profile-fields.index')
            ->with('success', 'فیلد جدید اضافه شد');
    }

    public function edit(ProfileField $profileField)
    {
        return view('admin.profile-fields.edit', compact('profileField'));
    }

    public function update(Request $request, ProfileField $profileField)
    {
        $profileField->update($request->only('label', 'key', 'type'));
        return redirect()->route('admin.profile-fields.index')->with('success', 'ویرایش شد');
    }

    public function destroy(ProfileField $profileField)
    {
        $profileField->delete();
        return redirect()->route('admin.profile-fields.index')->with('success', 'حذف شد');
    }
}
