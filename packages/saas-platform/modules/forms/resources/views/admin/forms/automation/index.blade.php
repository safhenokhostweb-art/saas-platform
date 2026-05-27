@extends(config('forms.layouts.admin'))

@section('header', 'خودکارسازی: ' . $form->title)

@section('content')
<div x-data="automationManager()" x-cloak>

  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-2">
      <a href="{{ route('admin.forms.builder', $form) }}"
         class="flex items-center gap-1.5 text-slate-500 hover:text-slate-800 text-sm transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        بازگشت به سازنده
      </a>
    </div>
    <button @click="showAddModal = true"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm hover:bg-indigo-700 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      قانون جدید
    </button>
  </div>

  {{-- Explanation --}}
  <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 mb-6 flex items-start gap-3">
    <svg class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
    <div>
      <p class="font-semibold text-indigo-800 text-sm">خودکارسازی هوشمند</p>
      <p class="text-xs text-indigo-600 mt-0.5">پس از هر ارسال، قوانین فعال اجرا می‌شوند. می‌توانید ایمیل، Webhook، Slack یا Telegram تنظیم کنید.</p>
    </div>
  </div>

  @if(session('success'))
  <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
  @endif

  {{-- Automation list --}}
  @if($automations->isEmpty())
  <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center">
    <svg class="w-12 h-12 mx-auto text-slate-200 mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
    <p class="text-slate-400 font-medium mb-1">هنوز قانون خودکارسازی اضافه نشده</p>
    <p class="text-slate-400 text-sm">اولین قانون را اضافه کنید تا پس از هر ارسال اجرا شود.</p>
    <button @click="showAddModal = true"
            class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm hover:bg-indigo-700 transition inline-flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      افزودن قانون
    </button>
  </div>
  @else
  <div class="space-y-3">
    @foreach($automations as $automation)
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
      {{-- Type icon --}}
      <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0
                  {{ match($automation->type) {
                     'email','autoresponder' => 'bg-blue-50',
                     'webhook'    => 'bg-slate-100',
                     'slack'      => 'bg-purple-50',
                     'telegram'   => 'bg-sky-50',
                     default      => 'bg-slate-100',
                  } }}">
        {{ \SaaSPlatform\Modules\Forms\Models\FormAutomation::typeIcon($automation->type) }}
      </div>

      {{-- Details --}}
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-0.5">
          <span class="font-semibold text-slate-800 text-sm">{{ $automation->name }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full {{ $automation->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
            {{ $automation->is_active ? 'فعال' : 'غیرفعال' }}
          </span>
        </div>
        <p class="text-xs text-slate-500">
          {{ \SaaSPlatform\Modules\Forms\Models\FormAutomation::typeLabel($automation->type) }}
          @if($automation->type === 'email' || $automation->type === 'autoresponder')
            — ارسال به: {{ $automation->config['to'] ?? '—' }}
          @elseif($automation->type === 'webhook')
            — URL: {{ \Illuminate\Support\Str::limit($automation->config['url'] ?? '—', 40) }}
          @endif
        </p>
      </div>

      {{-- Actions --}}
      <div class="flex items-center gap-2 shrink-0">
        <form method="POST" action="{{ route('admin.forms.automation.toggle', [$form, $automation]) }}">
          @csrf @method('PATCH')
          <button class="text-xs px-3 py-1.5 rounded-lg border transition
                         {{ $automation->is_active ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }}">
            {{ $automation->is_active ? 'غیرفعال کن' : 'فعال کن' }}
          </button>
        </form>
        <form method="POST" action="{{ route('admin.forms.automation.destroy', [$form, $automation]) }}"
              onsubmit="return confirm('این قانون حذف شود؟')">
          @csrf @method('DELETE')
          <button class="p-1.5 rounded-lg border border-red-100 text-red-400 hover:bg-red-50 hover:text-red-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
          </button>
        </form>
      </div>
    </div>
    @endforeach
  </div>
  @endif

  {{-- ════ ADD AUTOMATION MODAL ════ --}}
  <div x-show="showAddModal"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @click.self="showAddModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-800">افزودن قانون خودکارسازی</h3>
        <button @click="showAddModal = false" class="p-1 rounded hover:bg-slate-100 text-slate-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="POST" action="{{ route('admin.forms.automation.store', $form) }}" class="p-6 space-y-4">
        @csrf
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">نام قانون <span class="text-red-500">*</span></label>
          <input type="text" name="name" required
                 class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                 placeholder="مثلاً: اعلان به مدیر">
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">نوع</label>
          <select name="type" x-model="automationType"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            <option value="email">ایمیل به مدیر</option>
            <option value="autoresponder">پاسخ خودکار به کاربر</option>
            <option value="webhook">Webhook</option>
            <option value="slack">Slack</option>
            <option value="telegram">Telegram</option>
          </select>
        </div>

        {{-- Email/autoresponder config --}}
        <div x-show="automationType === 'email' || automationType === 'autoresponder'" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">آدرس ایمیل مقصد</label>
            <input type="email" name="config[to]"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr"
                   placeholder="admin@example.com">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">موضوع ایمیل</label>
            <input type="text" name="config[subject]"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                   placeholder="ارسال جدید از فرم {{ $form->title }}">
          </div>
        </div>

        {{-- Webhook config --}}
        <div x-show="automationType === 'webhook'">
          <label class="block text-xs font-medium text-slate-600 mb-1">Webhook URL</label>
          <input type="url" name="config[url]"
                 class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr"
                 placeholder="https://your-endpoint.com/webhook">
        </div>

        {{-- Slack config --}}
        <div x-show="automationType === 'slack'">
          <label class="block text-xs font-medium text-slate-600 mb-1">Slack Webhook URL</label>
          <input type="url" name="config[webhook_url]"
                 class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr"
                 placeholder="https://hooks.slack.com/services/...">
        </div>

        {{-- Telegram config --}}
        <div x-show="automationType === 'telegram'" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Bot Token</label>
            <input type="text" name="config[bot_token]"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr"
                   placeholder="123456:ABC-DEF...">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Chat ID</label>
            <input type="text" name="config[chat_id]"
                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none" dir="ltr"
                   placeholder="-100...">
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button type="button" @click="showAddModal = false"
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 transition">انصراف</button>
          <button type="submit"
                  class="px-5 py-2 bg-indigo-600 text-white text-sm rounded-xl hover:bg-indigo-700 active:scale-95 transition">
            ذخیره قانون
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('automationManager', () => ({
    showAddModal: false,
    automationType: 'email',
  }));
});
</script>
@endpush
@endsection
