@extends(config('forms.layouts.admin'))

@section('header', 'خودکارسازی: ' . $form->title)

@section('content')
{{--
  Phase 10.2 Frontend Governance: all raw controls replaced with x-core-ui::* canonical components.
--}}
<div x-data="automationManager()">

  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <x-core-ui::button variant="ghost" size="sm" href="{{ route('admin.forms.builder', $form) }}">
      <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
      بازگشت به سازنده
    </x-core-ui::button>

    <x-core-ui::button variant="primary" size="sm" @click="$dispatch('open-modal', 'add-automation')">
      <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      قانون جدید
    </x-core-ui::button>
  </div>

  {{-- Explanation --}}
  <x-core-ui::alert variant="info" class="mb-6">
    <strong>خودکارسازی هوشمند:</strong>
    پس از هر ارسال، قوانین فعال اجرا می‌شوند. می‌توانید ایمیل، Webhook، Slack یا Telegram تنظیم کنید.
  </x-core-ui::alert>

  @if(session('success'))
    <x-core-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-core-ui::alert>
  @endif

  {{-- Automation list --}}
  @if($automations->isEmpty())
    <x-core-ui::empty-state
      title="هنوز قانون خودکارسازی اضافه نشده"
      description="اولین قانون را اضافه کنید تا پس از هر ارسال اجرا شود."
    >
      <x-core-ui::button variant="primary" size="sm" class="mt-4" @click="$dispatch('open-modal', 'add-automation')">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        افزودن قانون
      </x-core-ui::button>
    </x-core-ui::empty-state>
  @else
    <div class="space-y-3">
      @foreach($automations as $automation)
        <x-core-ui::card class="p-5">
          <div class="flex items-center gap-4">
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
                <span class="font-semibold text-[var(--ui-text-primary)] text-sm">{{ $automation->name }}</span>
                <x-core-ui::badge :variant="$automation->is_active ? 'success' : 'neutral'">
                  {{ $automation->is_active ? 'فعال' : 'غیرفعال' }}
                </x-core-ui::badge>
              </div>
              <p class="text-xs text-[var(--ui-text-subtle)]">
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
                <x-core-ui::button
                  type="submit"
                  :variant="$automation->is_active ? 'ghost' : 'success'"
                  size="sm"
                >
                  {{ $automation->is_active ? 'غیرفعال کن' : 'فعال کن' }}
                </x-core-ui::button>
              </form>

              <form method="POST" action="{{ route('admin.forms.automation.destroy', [$form, $automation]) }}"
                    x-data x-on:submit.prevent="if(confirm('این قانون حذف شود؟')) $el.submit()">
                @csrf @method('DELETE')
                <x-core-ui::button type="submit" variant="ghost" size="sm" :icon="true" aria-label="حذف قانون">
                  <svg class="w-4 h-4 text-[var(--ui-danger)]" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg>
                </x-core-ui::button>
              </form>
            </div>
          </div>
        </x-core-ui::card>
      @endforeach
    </div>
  @endif

  {{-- ════ ADD AUTOMATION MODAL ════ --}}
  <x-core-ui::modal name="add-automation" title="افزودن قانون خودکارسازی" size="lg">
    <form method="POST" action="{{ route('admin.forms.automation.store', $form) }}" class="space-y-4">
      @csrf

      <x-core-ui::input
        label="نام قانون"
        name="name"
        :required="true"
        placeholder="مثلاً: اعلان به مدیر"
      />

      <x-core-ui::select
        label="نوع"
        name="type"
        x-model="automationType"
      >
        <option value="email">ایمیل به مدیر</option>
        <option value="autoresponder">پاسخ خودکار به کاربر</option>
        <option value="webhook">Webhook</option>
        <option value="slack">Slack</option>
        <option value="telegram">Telegram</option>
      </x-core-ui::select>

      {{-- Email/autoresponder config --}}
      <div x-show="automationType === 'email' || automationType === 'autoresponder'" class="space-y-3">
        <x-core-ui::input
          label="آدرس ایمیل مقصد"
          name="config[to]"
          type="email"
          placeholder="admin@example.com"
          dir="ltr"
        />
        <x-core-ui::input
          label="موضوع ایمیل"
          name="config[subject]"
          placeholder="ارسال جدید از فرم {{ $form->title }}"
        />
      </div>

      {{-- Webhook config --}}
      <div x-show="automationType === 'webhook'">
        <x-core-ui::input
          label="Webhook URL"
          name="config[url]"
          type="url"
          placeholder="https://your-endpoint.com/webhook"
          dir="ltr"
        />
      </div>

      {{-- Slack config --}}
      <div x-show="automationType === 'slack'">
        <x-core-ui::input
          label="Slack Webhook URL"
          name="config[webhook_url]"
          type="url"
          placeholder="https://hooks.slack.com/services/..."
          dir="ltr"
        />
      </div>

      {{-- Telegram config --}}
      <div x-show="automationType === 'telegram'" class="space-y-3">
        <x-core-ui::input
          label="Bot Token"
          name="config[bot_token]"
          placeholder="123456:ABC-DEF..."
          dir="ltr"
        />
        <x-core-ui::input
          label="Chat ID"
          name="config[chat_id]"
          placeholder="-100..."
          dir="ltr"
        />
      </div>

      <x-slot:footer>
        <x-core-ui::button type="button" variant="ghost" size="sm" @click="$dispatch('close-modal', 'add-automation')">
          انصراف
        </x-core-ui::button>
        <x-core-ui::button type="submit" variant="primary" size="sm">
          ذخیره قانون
        </x-core-ui::button>
      </x-slot:footer>
    </form>
  </x-core-ui::modal>

</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('automationManager', () => ({
    automationType: 'email',
  }));
});
</script>
@endpush
@endsection
