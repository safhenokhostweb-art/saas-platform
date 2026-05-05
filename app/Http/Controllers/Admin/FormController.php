<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index()
    {
        $forms = Form::latest()->get();
        return view('admin.forms.index', compact('forms'));
    }

    public function create()
    {
        return view('admin.forms.create');
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $form = Form::create([
            'title' => $request->title,
            'slug'  => Str::slug($request->title) . '-' . time(),
            'is_active' => $request->boolean('is_active'),
        ]);
        ActivityLogger::log('form.created', 'ایجاد فرم: ' . $form->title, $form);

        return redirect()->route('admin.forms.index')->with('success', 'فرم ساخته شد');
    }

    public function storeField(Request $request, Form $form)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'is_required' => 'nullable|boolean',
        ]);

        $data['is_required'] = $request->has('is_required');
        $data['is_active'] = true;
        $data['position'] = $form->fields()->count() + 1;

        $form->fields()->create($data);

        return back()->with('success', 'فیلد با موفقیت اضافه شد');
    }


    public function edit(Form $form)
    {
        $form->load('fields');
        return view('admin.forms.edit', compact('form'));
    }

    /** پیش‌نمایش فرم نهایی (همان‌طور که در سایت مشتری نمایش داده می‌شود) */
    public function preview(Form $form)
    {
        $form->load(['fields' => fn ($q) => $q->where('is_active', true)->orderBy('position')]);
        $captcha = ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'persian_code' => '', 'normal_text' => ''];
        if (CaptchaService::isModuleEnabled()) {
            $cfg = CaptchaService::getForSite(null);
            if ($cfg['enabled']) {
                $captcha['enabled'] = true;
                $captcha['type'] = $cfg['type'];
                $captcha['hcaptcha_site_key'] = $cfg['hcaptcha_site_key'];
                if ($cfg['type'] === CaptchaService::TYPE_PERSIAN_NUMBER) {
                    $captcha['persian_code'] = CaptchaService::generatePersianNumber(4);
                } elseif ($cfg['type'] === CaptchaService::TYPE_NORMAL) {
                    $gen = CaptchaService::generateNormal();
                    $captcha['normal_text'] = $gen['text'];
                }
            }
        }
        $firstSite = $form->sites()->first() ?? Site::orderBy('id')->first();
        return view('admin.forms.preview', compact('form', 'captcha', 'firstSite'));
    }

    public function update(Request $request, Form $form)
    {
        $form->update([
            'title' => $request->title,
            'is_active' => $request->boolean('is_active'),
        ]);
        ActivityLogger::log('form.updated', 'ویرایش فرم: ' . $form->title, $form);

        return back()->with('success', 'فرم ویرایش شد');
    }

    public function destroy(Form $form)
    {
        $title = $form->title;
        $id = $form->id;
        $form->fields()->delete();
        $form->delete();
        ActivityLogger::log('form.deleted', 'حذف فرم: ' . $title, null, ['deleted_id' => $id]);

        return redirect()->route('admin.forms.index')
            ->with('success', 'فرم حذف شد');
    }


    public function destroyField(FormField $field)
    {
        $field->delete();
        return back()->with('success', 'فیلد حذف شد');
    }

    public function toggleField(FormField $field)
    {
        $field->update([
            'is_active' => ! $field->is_active
        ]);

        return back();
    }

    public function sortFields(Request $request, Form $form)
    {
        foreach ($request->positions as $position => $id) {
            FormField::where('id', $id)->update(['position' => $position]);
        }

        return response()->json(['status' => 'ok']);
    }
}
