@extends(config('forms.layouts.admin'))

@section('header', 'فرم جدید')

@section('content')
<div x-data="createForm()" class="max-w-3xl mx-auto">

  {{-- Page intro --}}
  <div class="mb-8">
    <p class="text-slate-500 text-sm">یک نام برای فرم انتخاب کنید، سپس وارد سازنده می‌شوید.</p>
  </div>

  <form method="POST" action="{{ route('admin.forms.store') }}" @submit="submitting = true">
    @csrf

    {{-- ═══ MAIN CARD ═══ --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 mb-5">

      {{-- Form title --}}
      <div class="mb-6">
        <label class="block text-sm font-semibold text-slate-700 mb-2">
          نام فرم <span class="text-red-500">*</span>
        </label>
        <input type="text" name="title" id="title"
               value="{{ old('title') }}"
               autofocus
               required
               @input="syncSlug($event.target.value)"
               class="w-full border border-slate-200 rounded-xl px-4 py-3.5 text-slate-800 text-base
                      placeholder-slate-300 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400
                      outline-none transition"
               placeholder="مثلاً: فرم تماس با ما">
        @error('title')
          <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            {{ $message }}
          </p>
        @enderror
      </div>

      {{-- Slug --}}
      <div class="mb-6">
        <label class="block text-sm font-medium text-slate-600 mb-2">
          آدرس (Slug)
          <span class="text-slate-400 font-normal text-xs mr-1">— در صورت خالی بودن، خودکار ساخته می‌شود</span>
        </label>
        <div class="relative flex items-center">
          <span class="absolute right-4 text-slate-400 text-sm select-none">/f/</span>
          <input type="text" name="slug" id="slug"
                 value="{{ old('slug') }}"
                 x-model="slugValue"
                 class="w-full border border-slate-200 rounded-xl pr-12 pl-4 py-3 text-slate-700 text-sm
                        placeholder-slate-300 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400
                        outline-none transition font-mono"
                 placeholder="contact-form" dir="ltr">
        </div>
        @error('slug')
          <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>
        @enderror
      </div>

      {{-- Active toggle --}}
      <div class="flex items-center justify-between p-4 rounded-xl bg-slate-50 border border-slate-100">
        <div>
          <p class="text-sm font-medium text-slate-700">فرم فعال باشد</p>
          <p class="text-xs text-slate-400 mt-0.5">فرم فوری در دسترس کاربران قرار می‌گیرد</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                 {{ old('is_active', '1') ? 'checked' : '' }}>
          <div class="w-10 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300
                      rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white
                      after:content-[''] after:absolute after:top-[2px] after:right-[2px]
                      after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5
                      after:transition-all peer-checked:bg-indigo-600"></div>
        </label>
      </div>
    </div>

    {{-- ═══ STARTING POINT ═══ --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 mb-5">
      <h3 class="font-semibold text-slate-700 mb-1 text-sm">نقطه شروع</h3>
      <p class="text-xs text-slate-400 mb-5">از یک قالب آماده شروع کنید یا فرم خالی بسازید</p>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

        {{-- Blank form --}}
        <label class="relative cursor-pointer group">
          <input type="radio" name="_start" value="blank" x-model="startMode" class="sr-only peer">
          <div class="flex flex-col items-center gap-2 p-5 rounded-xl border-2 transition
                      peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                      border-slate-200 hover:border-slate-300 hover:bg-slate-50
                      group-focus-within:ring-2 group-focus-within:ring-indigo-200">
            <div class="w-10 h-10 rounded-xl bg-slate-100 peer-checked:bg-indigo-100 flex items-center justify-center
                        group-[.peer:checked~div]:bg-indigo-100">
              <svg class="w-5 h-5 text-slate-500 peer-checked:text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/>
              </svg>
            </div>
            <span class="text-xs font-semibold text-slate-700">فرم خالی</span>
            <span class="text-[10px] text-slate-400 text-center">از صفر شروع کنید</span>
          </div>
          {{-- Checkmark --}}
          <div class="absolute top-2 left-2 w-4 h-4 rounded-full bg-indigo-500 text-white items-center justify-center hidden peer-checked:flex">
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </div>
        </label>

        {{-- Contact template --}}
        <label class="relative cursor-pointer group">
          <input type="radio" name="_start" value="contact" x-model="startMode" class="sr-only peer">
          <input type="hidden" name="_builtin_template" value="0">
          <div class="flex flex-col items-center gap-2 p-5 rounded-xl border-2 transition
                      peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                      border-slate-200 hover:border-slate-300 hover:bg-slate-50">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
              </svg>
            </div>
            <span class="text-xs font-semibold text-slate-700">فرم تماس</span>
            <span class="text-[10px] text-slate-400 text-center">نام، ایمیل، پیام</span>
          </div>
          <div class="absolute top-2 left-2 w-4 h-4 rounded-full bg-indigo-500 text-white items-center justify-center hidden peer-checked:flex">
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </div>
        </label>

        {{-- Survey template --}}
        <label class="relative cursor-pointer group">
          <input type="radio" name="_start" value="survey" x-model="startMode" class="sr-only peer">
          <input type="hidden" name="_builtin_template" value="1">
          <div class="flex flex-col items-center gap-2 p-5 rounded-xl border-2 transition
                      peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                      border-slate-200 hover:border-slate-300 hover:bg-slate-50">
            <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
              </svg>
            </div>
            <span class="text-xs font-semibold text-slate-700">نظرسنجی</span>
            <span class="text-[10px] text-slate-400 text-center">رضایت و بازخورد</span>
          </div>
          <div class="absolute top-2 left-2 w-4 h-4 rounded-full bg-indigo-500 text-white items-center justify-center hidden peer-checked:flex">
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
          </div>
        </label>

      </div>

      {{-- Hidden fields for template routing --}}
      <input type="hidden" name="template" :value="templateIndex">
      <input type="hidden" name="builtin" :value="startMode !== 'blank' ? 1 : 0">
    </div>

    {{-- ═══ ACTIONS ═══ --}}
    <div class="flex items-center justify-between">
      <a href="{{ route('admin.forms.index') }}"
         class="flex items-center gap-1.5 text-slate-500 hover:text-slate-700 text-sm transition px-4 py-2.5 rounded-xl hover:bg-slate-100">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        انصراف
      </a>
      <button type="submit"
              :disabled="submitting"
              class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600
                     text-white rounded-xl text-sm font-semibold hover:opacity-90 active:scale-95
                     transition disabled:opacity-60 disabled:cursor-not-allowed shadow-md shadow-indigo-200">
        <template x-if="!submitting">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
          </svg>
        </template>
        <template x-if="submitting">
          <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
        </template>
        <span x-text="submitting ? 'در حال ساخت...' : 'ساخت فرم و رفتن به سازنده'"></span>
      </button>
    </div>

  </form>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('createForm', () => ({
    slugValue: '{{ old('slug', '') }}',
    startMode: 'blank',
    submitting: false,

    get templateIndex() {
      return this.startMode === 'contact' ? 0 : (this.startMode === 'survey' ? 1 : 0);
    },

    syncSlug(title) {
      if (this.slugValue && this.slugValue !== this._lastGeneratedSlug) return;
      const slug = title
        .toLowerCase()
        .replace(/[؀-ۿ\s]+/g, '-') // Persian chars → dash
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
      this._lastGeneratedSlug = slug;
      this.slugValue = slug;
    },
  }));
});
</script>
@endpush
@endsection
