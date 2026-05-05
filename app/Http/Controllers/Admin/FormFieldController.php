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
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'key'   => 'required|string|max:255|unique:profile_fields,key',
            'type'  => 'required|in:text,textarea,number,map',
        ]);

        ProfileField::create([
            'label'    => $data['label'],
            'key'      => $data['key'],
            'type'     => $data['type'],
            'required' => $request->boolean('required'),
            'enabled'  => true,
            'position' => ProfileField::max('position') + 1,
        ]);

        return redirect()->route('admin.profile-fields.index')->with('success', 'فیلد اضافه شد');
    }

    public function edit(ProfileField $profileField)
    {
        return view('admin.profile-fields.edit', compact('profileField'));
    }

    public function update(Request $request, ProfileField $profileField)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'key'   => 'required|string|max:255|unique:profile_fields,key,' . $profileField->id,
            'type'  => 'required|in:text,textarea,number,map',
        ]);

        $profileField->update([
            'label'    => $data['label'],
            'key'      => $data['key'],
            'type'     => $data['type'],
            'required' => $request->boolean('required'),
        ]);

        return redirect()->route('admin.profile-fields.index')->with('success', 'ویرایش شد');
    }

    public function destroy(ProfileField $profileField)
    {
        $profileField->delete();
        return back()->with('success', 'حذف شد');
    }

    public function toggleEnabled(ProfileField $profileField)
    {
        $profileField->update(['enabled' => ! $profileField->enabled]);
        return back()->with('success', 'وضعیت تغییر کرد');
    }

    public function toggleRequired(ProfileField $profileField)
    {
        $profileField->update(['required' => ! $profileField->required]);
        return back()->with('success', 'الزامی بودن تغییر کرد');
    }
}
