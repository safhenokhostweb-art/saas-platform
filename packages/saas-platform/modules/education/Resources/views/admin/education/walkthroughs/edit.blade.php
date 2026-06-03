@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', $walkthrough ? 'ویرایش راهنما' : 'راهنمای جدید')

@section('content')
<div class="mx-auto max-w-2xl space-y-6" dir="rtl"
     x-data="{
        steps: @json($walkthrough?->steps?->toArray() ?? []),
        addStep() { this.steps.push({ title:'', body:'', target_selector:'', placement:'bottom', action:'next' }); },
        removeStep(i) { this.steps.splice(i, 1); }
     }">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.education.walkthroughs.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-900">{{ $walkthrough ? 'ویرایش راهنما' : 'راهنمای جدید' }}</h1>
    </div>

    <form method="POST"
          action="{{ $walkthrough ? route('admin.education.walkthroughs.update', $walkthrough) : route('admin.education.walkthroughs.store') }}"
          class="space-y-5">
        @csrf
        @if($walkthrough) @method('PUT') @endif

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان</label>
                <input type="text" name="title" value="{{ old('title', $walkthrough?->title) }}" required
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیح</label>
                <textarea name="description" rows="2"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm resize-none focus:border-violet-400 focus:outline-none">{{ old('description', $walkthrough?->description) }}</textarea>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trigger Route</label>
                    <input type="text" name="trigger_route" value="{{ old('trigger_route', $walkthrough?->trigger_route) }}"
                           placeholder="admin.tickets.index"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono focus:border-violet-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شرط نمایش</label>
                    <select name="trigger_condition" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
                        <option value="once"   {{ old('trigger_condition', $walkthrough?->trigger_condition ?? 'once') === 'once'   ? 'selected':'' }}>یک‌بار</option>
                        <option value="always" {{ old('trigger_condition', $walkthrough?->trigger_condition) === 'always' ? 'selected':'' }}>همیشه</option>
                        <option value="manual" {{ old('trigger_condition', $walkthrough?->trigger_condition) === 'manual' ? 'selected':'' }}>دستی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">slug ماژول</label>
                    <input type="text" name="module_slug" value="{{ old('module_slug', $walkthrough?->module_slug) }}"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $walkthrough?->is_active ?? true) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                فعال
            </label>
        </div>

        {{-- Steps Builder --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">گام‌ها</h2>
                <button type="button" x-on:click="addStep()"
                        class="rounded-lg border border-violet-200 px-3 py-1 text-xs text-violet-700 hover:bg-violet-50">+ گام جدید</button>
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

                    <textarea x-bind:name="'steps[' + i + '][body]'" x-model="step.body" rows="2"
                              placeholder="توضیح گام"
                              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm resize-none focus:outline-none"></textarea>

                    <div class="flex gap-3">
                        <input type="text" x-bind:name="'steps[' + i + '][target_selector]'" x-model="step.target_selector"
                               placeholder="CSS selector (اختیاری)"
                               class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-mono focus:outline-none">
                        <select x-bind:name="'steps[' + i + '][placement]'" x-model="step.placement"
                                class="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs focus:outline-none">
                            <option value="bottom">پایین</option>
                            <option value="top">بالا</option>
                            <option value="left">چپ</option>
                            <option value="right">راست</option>
                            <option value="center">مرکز</option>
                        </select>
                        <select x-bind:name="'steps[' + i + '][action]'" x-model="step.action"
                                class="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs focus:outline-none">
                            <option value="next">بعدی</option>
                            <option value="click">کلیک</option>
                            <option value="input">ورودی</option>
                            <option value="observe">مشاهده</option>
                        </select>
                    </div>
                </div>
            </template>

            <p x-show="steps.length === 0" class="text-center text-sm text-gray-400 py-4">گامی تعریف نشده. روی «+ گام جدید» کلیک کنید.</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.education.walkthroughs.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">انصراف</a>
            <button type="submit" class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700">ذخیره راهنما</button>
        </div>
    </form>
</div>
@endsection
