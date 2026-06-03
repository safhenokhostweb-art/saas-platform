@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', $path ? 'ویرایش مسیر یادگیری' : 'مسیر یادگیری جدید')

@section('content')
<div class="mx-auto max-w-2xl space-y-6" dir="rtl"
     x-data="{
        steps: @json($path?->steps?->toArray() ?? []),
        addStep() { this.steps.push({ title:'', description:'', step_type:'article', reference_id:null, is_required:true }); },
        removeStep(i) { this.steps.splice(i, 1); }
     }">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.education.learning-paths.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">{{ $path ? 'ویرایش مسیر یادگیری' : 'مسیر جدید' }}</h1>
    </div>

    <form method="POST"
          action="{{ $path ? route('admin.education.learning-paths.update', $path) : route('admin.education.learning-paths.store') }}"
          class="space-y-5">
        @csrf
        @if($path) @method('PUT') @endif

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان مسیر</label>
                <input type="text" name="title" value="{{ old('title', $path?->title) }}" required
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیح</label>
                <textarea name="description" rows="2"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm resize-none focus:border-emerald-400 focus:outline-none">{{ old('description', $path?->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">زمان تقریبی (دقیقه)</label>
                    <input type="number" name="estimated_minutes" value="{{ old('estimated_minutes', $path?->estimated_minutes) }}" min="1"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">slug ماژول</label>
                    <input type="text" name="module_slug" value="{{ old('module_slug', $path?->module_slug) }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $path?->is_active ?? true) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                فعال
            </label>
        </div>

        {{-- Steps --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">گام‌های مسیر</h2>
                <button type="button" x-on:click="addStep()"
                        class="rounded-lg border border-emerald-200 px-3 py-1 text-xs text-emerald-700 hover:bg-emerald-50">+ گام جدید</button>
            </div>

            <template x-for="(step, i) in steps" :key="i">
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 space-y-3 relative">
                    <button type="button" x-on:click="removeStep(i)"
                            class="absolute left-3 top-3 text-gray-400 hover:text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <div class="text-xs font-medium text-gray-500" x-text="'گام ' + (i + 1)"></div>

                    <input type="text" x-bind:name="'steps[' + i + '][title]'" x-model="step.title"
                           placeholder="عنوان گام" required
                           class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm focus:outline-none">

                    <div class="flex gap-3">
                        <select x-bind:name="'steps[' + i + '][step_type]'" x-model="step.step_type"
                                class="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs focus:outline-none">
                            <option value="article">مقاله</option>
                            <option value="walkthrough">راهنمای گام‌به‌گام</option>
                            <option value="task">وظیفه</option>
                            <option value="external_link">لینک خارجی</option>
                        </select>
                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                            <input type="hidden" x-bind:name="'steps[' + i + '][is_required]'" value="0">
                            <input type="checkbox" x-bind:name="'steps[' + i + '][is_required]'" value="1"
                                   x-model="step.is_required"
                                   class="rounded border-gray-300 text-emerald-600">
                            اجباری
                        </label>
                    </div>
                </div>
            </template>

            <p x-show="steps.length === 0" class="text-center text-sm text-gray-400 py-4">گامی تعریف نشده.</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.education.learning-paths.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">انصراف</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">ذخیره مسیر</button>
        </div>
    </form>
</div>
@endsection
