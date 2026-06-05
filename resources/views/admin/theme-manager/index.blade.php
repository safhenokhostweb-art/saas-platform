@extends('layouts.admin')

@section('title', 'مدیریت تم | Enterprise Theme Engine')

@push('head')
<style>
/* ── Theme Manager Hover & Cursor States ─────────────────────────────── */
.tm-btn                { cursor:pointer; transition:filter .15s,opacity .15s,background .15s; }
.tm-btn:hover          { filter:brightness(1.1); }
.tm-btn-ghost          { cursor:pointer; transition:background .15s,color .15s; }
.tm-btn-ghost:hover    { background:var(--ui-surface-muted); color:var(--ui-text-primary); }
.tm-card               { transition:box-shadow .2s,border-color .2s,transform .15s; }
.tm-card:hover         { transform:translateY(-2px); box-shadow:var(--ui-shadow-card-hover); }
.tm-card:hover .tm-activate-btn { opacity:1; }
.tm-activate-btn       { cursor:pointer; transition:background .15s,filter .15s; }
.tm-activate-btn:hover { filter:brightness(1.12); }
.tm-preview-btn        { cursor:pointer; transition:background .15s,color .15s,border-color .15s; }
.tm-preview-btn:hover  { background:var(--ui-surface-muted); border-color:var(--ui-border-strong); color:var(--ui-text-primary); }
.tm-dot-btn            { cursor:pointer; transition:background .15s; }
.tm-dot-btn:hover      { background:var(--ui-surface-muted); }
.tm-dropdown-item      { cursor:pointer; transition:background .15s; }
.tm-dropdown-item:hover{ background:var(--ui-surface-soft); }
.tm-danger-item        { cursor:pointer; transition:background .15s; }
.tm-danger-item:hover  { background:var(--ui-danger-light); }
.tm-modal-overlay      { background:rgba(0,0,0,.45); }
</style>
@endpush

@section('content')
<div class="min-h-screen" style="background:var(--ui-bg);">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold" style="color:var(--ui-text-primary);">مدیریت تم پلتفرم</h1>
        <p class="mt-1 text-sm" style="color:var(--ui-text-muted);">تم فعال بر کل داشبورد، نوار کناری، هدر، دکمه‌ها، و کلاینت موبایل تأثیر می‌گذارد.</p>
      </div>
      <div class="flex items-center gap-3">
        <form action="{{ route('admin.theme-manager.reset-default') }}" method="POST"
              onsubmit="return confirm('بازنشانی به Enterprise Blue؟')">
          @csrf
          <button type="submit" class="tm-btn-ghost inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border"
                  style="border-color:var(--ui-border);color:var(--ui-text-muted);background:var(--ui-surface);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            بازنشانی به پیش‌فرض
          </button>
        </form>
        <a href="{{ route('admin.theme-manager.create') }}"
           class="tm-btn inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white"
           style="background:var(--ui-indigo);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          تم جدید
        </a>
      </div>
    </div>

    {{-- Active Theme Banner --}}
    @if($active)
    <div class="mb-6 p-4 rounded-xl flex items-center gap-4"
         style="background:var(--ui-indigo-light);border:1px solid var(--ui-indigo-ring);">
      <div class="w-9 h-9 rounded-xl shrink-0 flex items-center justify-center"
           style="background:var(--ui-indigo);color:#fff;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
      </div>
      <div>
        <p class="text-sm font-semibold" style="color:var(--ui-indigo);">تم فعال پلتفرم: {{ $active->name }}</p>
        <p class="text-xs mt-0.5" style="color:var(--ui-text-muted);">{{ $active->description }}</p>
      </div>
    </div>
    @endif

    {{-- Theme Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      @foreach($profiles as $profile)
      @php
        $isActive = $profile->is_active;
        $assignedSites = $siteAssignments->filter(fn($o) => $o->theme_profile_id === $profile->id);
      @endphp

      <div class="tm-card rounded-xl border overflow-hidden"
           style="background:var(--ui-surface);border-color:{{ $isActive ? 'var(--ui-indigo)' : 'var(--ui-border)' }};box-shadow:{{ $isActive ? 'var(--ui-shadow-indigo)' : 'var(--ui-shadow-sm)' }};">

        {{-- Color Preview Strip --}}
        <div class="h-16 relative overflow-hidden" style="background:linear-gradient(135deg,
          {{ $profile->sidebar['bg'] ?? '#1e293b' }} 0%,
          {{ $profile->sidebar['bg_bottom'] ?? '#0f172a' }} 50%,
          {{ $profile->colors['primary']['500'] ?? '#3b82f6' }} 100%);">
          <div class="absolute bottom-2 left-2 flex gap-1.5">
            <span class="w-5 h-5 rounded-full border-2 shadow" style="background:{{ $profile->colors['accent']['base'] ?? '#4f46e5' }};border-color:rgba(255,255,255,.4)"></span>
            <span class="w-5 h-5 rounded-full border-2 shadow" style="background:{{ $profile->colors['primary']['500'] ?? '#3b82f6' }};border-color:rgba(255,255,255,.4)"></span>
            <span class="w-5 h-5 rounded-full border-2 shadow" style="background:{{ $profile->colors['success']['base'] ?? '#16a34a' }};border-color:rgba(255,255,255,.4)"></span>
            <span class="w-5 h-5 rounded-full border-2 shadow" style="background:{{ $profile->colors['danger']['base'] ?? '#dc2626' }};border-color:rgba(255,255,255,.4)"></span>
          </div>
          @if($isActive)
          <div class="absolute top-2 right-2 flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold" style="background:var(--ui-indigo);color:#fff;">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            فعال
          </div>
          @endif
          @if($profile->is_system)
          <div class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-medium" style="background:rgba(0,0,0,.4);color:#fff;">سیستمی</div>
          @endif
          @if($assignedSites->count())
          <div class="absolute bottom-2 right-2 flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs" style="background:rgba(0,0,0,.4);color:#fff;">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
            {{ $assignedSites->count() }} سایت
          </div>
          @endif
        </div>

        {{-- Card Body --}}
        <div class="p-4 pb-2">
          <h3 class="font-semibold text-sm truncate" style="color:var(--ui-text-primary);">{{ $profile->name }}</h3>
          @if($profile->description)
          <p class="text-xs mt-1 line-clamp-2" style="color:var(--ui-text-muted);">{{ $profile->description }}</p>
          @endif
        </div>

        {{-- Actions --}}
        <div class="px-4 pb-4 flex items-center gap-2">
          {{-- Preview --}}
          <button type="button" class="tm-preview-btn flex-1 inline-flex justify-center items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border"
                  style="border-color:var(--ui-border);color:var(--ui-text-muted);background:var(--ui-surface-soft);"
                  onclick="applyThemePreview('{{ route('admin.theme-manager.preview-tokens', $profile) }}')">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            پیش‌نمایش
          </button>

          @if(!$isActive)
          <form action="{{ route('admin.theme-manager.activate', $profile) }}" method="POST">
            @csrf
            <button type="submit" class="tm-activate-btn inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg text-white"
                    style="background:var(--ui-indigo);">فعال‌سازی</button>
          </form>
          @endif

          {{-- 3-dot Dropdown --}}
          <div class="relative" x-data="{ open: false }">
            <button type="button" class="tm-dot-btn inline-flex items-center justify-center w-8 h-8 rounded-lg border"
                    style="border-color:var(--ui-border);color:var(--ui-text-muted);background:var(--ui-surface-soft);"
                    @click="open = !open" @click.outside="open = false" aria-label="گزینه‌ها">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
            </button>
            <div x-show="open" x-transition
                 class="absolute left-0 mt-1 w-44 rounded-xl shadow-lg z-20 border overflow-hidden"
                 style="background:var(--ui-surface);border-color:var(--ui-border);box-shadow:var(--ui-shadow-dropdown);"
                 x-cloak>
              {{-- Edit --}}
              <a href="{{ route('admin.theme-manager.edit', $profile) }}"
                 class="tm-dropdown-item flex items-center gap-2 px-3 py-2 text-sm"
                 style="color:var(--ui-text-primary);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش رنگ‌ها
              </a>
              {{-- Assign to Sites --}}
              <button type="button" class="tm-dropdown-item flex items-center gap-2 px-3 py-2 text-sm w-full text-right"
                      style="color:var(--ui-text-primary);"
                      @click="open=false; $dispatch('open-assign-modal', { id: {{ $profile->id }}, name: '{{ addslashes($profile->name) }}' })">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                اختصاص به سایت‌ها
              </button>
              {{-- Clone --}}
              <form action="{{ route('admin.theme-manager.clone', $profile) }}" method="POST">
                @csrf
                <input type="hidden" name="name" value="{{ $profile->name }} — کپی">
                <button type="submit" class="tm-dropdown-item flex items-center gap-2 px-3 py-2 text-sm w-full text-right"
                        style="color:var(--ui-text-primary);">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  کلون
                </button>
              </form>
              {{-- Delete (custom only) --}}
              @if(!$profile->is_system)
              <div class="border-t" style="border-color:var(--ui-border);"></div>
              <form action="{{ route('admin.theme-manager.destroy', $profile) }}" method="POST"
                    onsubmit="return confirm('حذف تم «{{ $profile->name }}»؟')">
                @csrf @method('DELETE')
                <button type="submit" class="tm-danger-item flex items-center gap-2 px-3 py-2 text-sm w-full text-right"
                        style="color:var(--ui-danger);">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  حذف
                </button>
              </form>
              @endif
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>

  </div>
</div>

{{-- ═══ Assign to Sites Modal ════════════════════════════════════════════════ --}}
{{--
  سایت‌های اختصاص‌داده‌شده به هر پروفایل — به صورت JSON برای Alpine
  ساختار: { site_id: theme_profile_id, ... }
--}}
@php
  $assignMap = $siteAssignments->mapWithKeys(fn($o, $k) => [(string)$k => (int)$o->theme_profile_id])->toArray();
@endphp

<div id="assign-modal-root"
     x-data="{
       show: false,
       profileId: 0,
       profileName: '',
       assignMap: @js($assignMap),
       get formAction() { return '{{ url('admin/theme-manager') }}/' + this.profileId + '/assign-to-sites'; },
       open(id, name) {
         this.profileId   = id;
         this.profileName = name;
         // Update checkbox states
         this.$nextTick(() => {
           document.querySelectorAll('#assign-modal-form input[type=checkbox]').forEach(cb => {
             cb.checked = this.assignMap[cb.value] === id;
           });
         });
         this.show = true;
         document.body.style.overflow = 'hidden';
       },
       close() { this.show = false; document.body.style.overflow = ''; }
     }"
     @open-assign-modal.window="open($event.detail.id, $event.detail.name)">

  {{-- Overlay --}}
  <div x-show="show" x-transition.opacity
       class="tm-modal-overlay fixed inset-0 z-40"
       @click="close()" x-cloak></div>

  {{-- Modal --}}
  <div x-show="show" x-transition
       class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
    <div class="w-full max-w-md rounded-2xl overflow-hidden"
         style="background:var(--ui-surface);box-shadow:var(--ui-shadow-modal);border:1px solid var(--ui-border);"
         @click.stop>

      {{-- Header --}}
      <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color:var(--ui-border);">
        <div>
          <h3 class="font-semibold text-sm" style="color:var(--ui-text-primary);">اختصاص تم به سایت‌ها</h3>
          <p class="text-xs mt-0.5" style="color:var(--ui-text-muted);" x-text="profileName"></p>
        </div>
        <button type="button" class="tm-dot-btn w-8 h-8 flex items-center justify-center rounded-lg"
                style="color:var(--ui-text-muted);" @click="close()">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      {{-- Form --}}
      <form id="assign-modal-form" method="POST" :action="formAction">
        @csrf
        <div class="px-6 py-4 max-h-72 overflow-y-auto">
          @if($sites->isEmpty())
            <p class="text-sm text-center py-6" style="color:var(--ui-text-muted);">هیچ سایتی ثبت نشده است.</p>
          @else
            <p class="text-xs mb-4" style="color:var(--ui-text-muted);">
              سایت‌هایی را که این تم باید برای آن‌ها فعال باشد انتخاب کنید.
              حذف تیک = برگشت به تم پلتفرم.
            </p>
            <div class="flex flex-col gap-2">
              @foreach($sites as $site)
              @php $curAssignedId = (int)($siteAssignments[$site->id]->theme_profile_id ?? 0); @endphp
              <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);"
                     onmouseover="this.style.borderColor='var(--ui-indigo-ring)'"
                     onmouseout="this.style.borderColor='var(--ui-border)'">
                <input type="checkbox"
                       name="site_ids[]"
                       value="{{ $site->id }}"
                       class="w-4 h-4 rounded"
                       style="accent-color:var(--ui-indigo);"
                       {{ $curAssignedId > 0 ? 'data-assigned="'.$curAssignedId.'"' : '' }}>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate" style="color:var(--ui-text-primary);">{{ $site->title }}</p>
                  <p class="text-xs truncate" style="color:var(--ui-text-muted);">{{ $site->domain }}</p>
                </div>
                @if($curAssignedId)
                <span class="text-xs px-2 py-0.5 rounded-full shrink-0"
                      style="background:var(--ui-indigo-light);color:var(--ui-indigo);">
                  {{ $siteAssignments[$site->id]->themeProfile->name ?? '—' }}
                </span>
                @endif
              </label>
              @endforeach
            </div>
          @endif
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t gap-3" style="border-color:var(--ui-border);background:var(--ui-surface-soft);">
          <button type="button" class="tm-btn-ghost px-4 py-2 text-sm rounded-lg border"
                  style="border-color:var(--ui-border);color:var(--ui-text-muted);"
                  @click="close()">انصراف</button>
          <button type="submit" class="tm-btn px-5 py-2 text-sm font-semibold rounded-lg text-white"
                  style="background:var(--ui-indigo);">
            ذخیره اختصاص
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Live Preview Toast --}}
<div id="theme-preview-toast" class="fixed bottom-6 right-6 z-50 hidden"
     style="background:var(--ui-surface);border:1px solid var(--ui-border);border-radius:var(--ui-radius-xl);box-shadow:var(--ui-shadow-toast);padding:1rem 1.25rem;min-width:260px;">
  <div class="flex items-center justify-between gap-4">
    <div>
      <p class="text-sm font-medium" id="preview-toast-name" style="color:var(--ui-text-primary);">پیش‌نمایش فعال</p>
      <p class="text-xs mt-0.5" style="color:var(--ui-text-muted);">ذخیره نشده — فقط نمایش است</p>
    </div>
    <button type="button" onclick="cancelPreview()" class="tm-btn-ghost text-xs px-3 py-1.5 rounded-lg border"
            style="border-color:var(--ui-border);color:var(--ui-text-muted);">بستن</button>
  </div>
</div>

<script>
const SIDEBAR_SCOPED_VARS = [
  '--platform-sidebar-bg-top','--platform-sidebar-bg-bottom',
  '--platform-sidebar-text','--platform-sidebar-muted',
  '--platform-sidebar-accent','--platform-sidebar-border',
  '--platform-sidebar-hover','--platform-sidebar-active',
];
let originalTokens = null;

async function applyThemePreview(url) {
  try {
    const res  = await fetch(url, { headers: { 'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content } });
    const data = await res.json();
    if (!data.tokens) return;
    const root    = document.documentElement;
    const sidebar = document.getElementById('admin-sidebar');
    if (!originalTokens) {
      originalTokens = { root:{}, sidebar:{} };
      const cs = getComputedStyle(root);
      Object.keys(data.tokens).forEach(k => originalTokens.root[k] = cs.getPropertyValue(k).trim());
      if (sidebar) SIDEBAR_SCOPED_VARS.forEach(k => originalTokens.sidebar[k] = sidebar.style.getPropertyValue(k) || getComputedStyle(sidebar).getPropertyValue(k).trim());
    }
    Object.entries(data.tokens).forEach(([k,v]) => root.style.setProperty(k,v));
    if (sidebar) {
      const sv = {};
      SIDEBAR_SCOPED_VARS.forEach(k => { if(data.tokens[k]) sv[k]=data.tokens[k]; });
      const accent = sv['--platform-sidebar-accent'];
      if (accent) {
        sv['--platform-sidebar-hover']  = `color-mix(in srgb, ${accent} 20%, transparent)`;
        sv['--platform-sidebar-active'] = `color-mix(in srgb, ${accent} 32%, transparent)`;
      }
      Object.entries(sv).forEach(([k,v]) => sidebar.style.setProperty(k,v));
    }
    document.getElementById('preview-toast-name').textContent = 'پیش‌نمایش: ' + (data.name||'');
    document.getElementById('theme-preview-toast').classList.remove('hidden');
  } catch(e) { console.error('Preview failed',e); }
}

function cancelPreview() {
  if (originalTokens) {
    const root    = document.documentElement;
    const sidebar = document.getElementById('admin-sidebar');
    Object.entries(originalTokens.root).forEach(([k,v]) => root.style.setProperty(k,v));
    if (sidebar) Object.entries(originalTokens.sidebar).forEach(([k,v]) => { if(v) sidebar.style.setProperty(k,v); else sidebar.style.removeProperty(k); });
    originalTokens = null;
  }
  document.getElementById('theme-preview-toast').classList.add('hidden');
}
</script>
@endsection
