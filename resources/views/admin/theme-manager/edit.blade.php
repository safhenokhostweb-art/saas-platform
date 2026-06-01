@extends('layouts.admin')

@section('title', $isNew ? 'تم جدید' : 'ویرایش تم: ' . $profile->name)

@section('content')
@php
  $action = $isNew
    ? route('admin.theme-manager.store')
    : route('admin.theme-manager.update', $profile);
  $method = $isNew ? 'POST' : 'PUT';

  // Defaults for new theme or current values for edit
  $colors     = $profile?->colors    ?? [];
  $surfaces   = $profile?->surfaces  ?? [];
  $typography = $profile?->typography ?? [];
  $borders    = $profile?->borders   ?? [];
  $sidebar    = $profile?->sidebar   ?? [];
  $header     = $profile?->header    ?? [];
@endphp

<div class="min-h-screen" style="background:var(--ui-bg);">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Page Header --}}
    <div class="flex items-center gap-3 mb-8">
      <a href="{{ route('admin.theme-manager.index') }}"
         class="w-8 h-8 inline-flex items-center justify-center rounded-lg border transition"
         style="border-color:var(--ui-border);color:var(--ui-text-muted);background:var(--ui-surface);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold" style="color:var(--ui-text-primary);">
          {{ $isNew ? 'ایجاد تم سفارشی' : 'ویرایش تم: ' . $profile->name }}
        </h1>
        @if(!$isNew && $profile->is_system)
        <p class="text-xs mt-1" style="color:var(--ui-warning);">تم سیستمی — تغییرات محدود به رنگ‌ها است</p>
        @endif
      </div>
    </div>

    <form action="{{ $action }}" method="POST" id="theme-form">
      @csrf
      @if($method === 'PUT') @method('PUT') @endif

      {{-- Basic Info --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">اطلاعات پایه</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1.5" style="color:var(--ui-text-secondary);">نام تم *</label>
            <input type="text" name="name" required
                   value="{{ old('name', $profile?->name) }}"
                   class="w-full px-3 py-2 rounded-lg border text-sm"
                   style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1.5" style="color:var(--ui-text-secondary);">توضیحات</label>
            <input type="text" name="description"
                   value="{{ old('description', $profile?->description) }}"
                   class="w-full px-3 py-2 rounded-lg border text-sm"
                   style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);">
          </div>
        </div>
      </div>

      {{-- Color Groups --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">گروه‌های رنگی</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          @foreach([
            ['colors[primary][500]', 'رنگ اصلی (Primary)', $colors['primary']['500'] ?? '#3b82f6'],
            ['colors[accent][base]',  'تأکید (Accent)',      $colors['accent']['base'] ?? '#4f46e5'],
            ['colors[secondary][base]', 'ثانویه (Secondary)', $colors['secondary']['base'] ?? '#a855f7'],
            ['colors[success][base]', 'موفقیت', $colors['success']['base'] ?? '#16a34a'],
            ['colors[warning][base]', 'هشدار',  $colors['warning']['base'] ?? '#d97706'],
            ['colors[danger][base]',  'خطر',    $colors['danger']['base']  ?? '#dc2626'],
            ['colors[info][base]',    'اطلاعات',$colors['info']['base']    ?? '#2563eb'],
          ] as [$name, $label, $value])
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:var(--ui-text-muted);">{{ $label }}</label>
            <div class="flex items-center gap-2">
              <input type="color" value="{{ old($name, $value) }}"
                     class="w-10 h-10 rounded-lg border cursor-pointer"
                     style="border-color:var(--ui-border);padding:2px;">
              <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                     class="flex-1 px-2 py-1.5 rounded-lg border text-xs font-mono"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);"
                     pattern="^#[0-9a-fA-F]{6}$" placeholder="#ffffff"
                     oninput="syncColor(this)">
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Surface Colors --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">رنگ‌های سطح</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          @foreach([
            ['surfaces[bg]',            'پس‌زمینه اصلی',    $surfaces['bg'] ?? '#f1f5f9'],
            ['surfaces[surface]',        'سطح (Surface)',    $surfaces['surface'] ?? '#ffffff'],
            ['surfaces[surface_soft]',   'سطح ملایم',        $surfaces['surface_soft'] ?? '#f8fafc'],
          ] as [$name, $label, $value])
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:var(--ui-text-muted);">{{ $label }}</label>
            <div class="flex items-center gap-2">
              <input type="color" value="{{ old($name, $value) }}"
                     class="w-10 h-10 rounded-lg border cursor-pointer"
                     style="border-color:var(--ui-border);padding:2px;">
              <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                     class="flex-1 px-2 py-1.5 rounded-lg border text-xs font-mono"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);"
                     pattern="^#[0-9a-fA-F]{6}$" oninput="syncColor(this)">
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Sidebar Colors --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">نوار کناری</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          @foreach([
            ['sidebar[bg]',        'بالای گرادیان', $sidebar['bg'] ?? '#1e293b'],
            ['sidebar[bg_bottom]', 'پایین گرادیان', $sidebar['bg_bottom'] ?? '#0f172a'],
            ['sidebar[text]',      'متن لینک‌ها',   $sidebar['text'] ?? '#f1f5f9'],
            ['sidebar[muted]',     'برچسب گروه',    $sidebar['muted'] ?? '#94a3b8'],
            ['sidebar[accent]',    'تأکید و آیکن',  $sidebar['accent'] ?? '#60a5fa'],
            ['sidebar[border]',    'حاشیه',         $sidebar['border'] ?? '#334155'],
          ] as [$name, $label, $value])
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:var(--ui-text-muted);">{{ $label }}</label>
            <div class="flex items-center gap-2">
              <input type="color" value="{{ old($name, $value) }}"
                     class="w-10 h-10 rounded-lg border cursor-pointer"
                     style="border-color:var(--ui-border);padding:2px;">
              <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                     class="flex-1 px-2 py-1.5 rounded-lg border text-xs font-mono"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);"
                     pattern="^#[0-9a-fA-F]{6}$" oninput="syncColor(this)">
            </div>
          </div>
          @endforeach
        </div>

        {{-- Sidebar Preview --}}
        <div class="mt-4 rounded-xl p-4 text-sm" id="sidebar-preview"
             style="background: linear-gradient(165deg, {{ $sidebar['bg'] ?? '#1e293b' }} 0%, {{ $sidebar['bg_bottom'] ?? '#0f172a' }} 100%);max-width:240px;">
          <div class="font-bold mb-2" style="color: {{ $sidebar['text'] ?? '#f1f5f9' }};">پیش‌نمایش نوار کناری</div>
          <div class="text-xs mb-1 opacity-70" style="color: {{ $sidebar['muted'] ?? '#94a3b8' }};">گروه منو</div>
          <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg" style="color: {{ $sidebar['text'] ?? '#f1f5f9' }};">
            <span style="color: {{ $sidebar['accent'] ?? '#60a5fa' }};">●</span> آیتم فعال
          </div>
        </div>
      </div>

      {{-- Header Colors --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">هدر</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          @foreach([
            ['header[bg]',          'پس‌زمینه هدر',    $header['bg'] ?? '#ffffff'],
            ['header[text]',         'متن هدر',         $header['text'] ?? '#374151'],
            ['header[link_active]',  'لینک فعال',       $header['link_active'] ?? '#4f46e5'],
          ] as [$name, $label, $value])
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:var(--ui-text-muted);">{{ $label }}</label>
            <div class="flex items-center gap-2">
              <input type="color" value="{{ old($name, $value) }}"
                     class="w-10 h-10 rounded-lg border cursor-pointer"
                     style="border-color:var(--ui-border);padding:2px;">
              <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                     class="flex-1 px-2 py-1.5 rounded-lg border text-xs font-mono"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);"
                     pattern="^#[0-9a-fA-F]{6}$" oninput="syncColor(this)">
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Typography Colors --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">تایپوگرافی</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          @foreach([
            ['typography[text_primary]',   'متن اصلی',    $typography['text_primary']   ?? '#0f172a'],
            ['typography[text_secondary]', 'متن ثانویه',  $typography['text_secondary'] ?? '#334155'],
            ['typography[text_muted]',     'متن کم‌رنگ',  $typography['text_muted']     ?? '#64748b'],
            ['typography[link]',           'رنگ لینک',    $typography['link']           ?? '#4f46e5'],
            ['typography[link_hover]',     'لینک هاور',   $typography['link_hover']     ?? '#4338ca'],
          ] as [$name, $label, $value])
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:var(--ui-text-muted);">{{ $label }}</label>
            <div class="flex items-center gap-2">
              <input type="color" value="{{ old($name, $value) }}"
                     class="w-10 h-10 rounded-lg border cursor-pointer"
                     style="border-color:var(--ui-border);padding:2px;">
              <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                     class="flex-1 px-2 py-1.5 rounded-lg border text-xs font-mono"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);"
                     pattern="^#[0-9a-fA-F]{6}$" oninput="syncColor(this)">
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Borders --}}
      <div class="rounded-xl border p-6 mb-6" style="background:var(--ui-surface);border-color:var(--ui-border);">
        <h2 class="font-semibold mb-4" style="color:var(--ui-text-primary);">حاشیه‌ها</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          @foreach([
            ['borders[border]',  'حاشیه اصلی',  $borders['border']  ?? '#e2e8f0'],
            ['borders[divider]', 'خط تقسیم',   $borders['divider'] ?? '#f1f5f9'],
            ['borders[strong]',  'حاشیه پررنگ', $borders['strong']  ?? '#cbd5e1'],
          ] as [$name, $label, $value])
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:var(--ui-text-muted);">{{ $label }}</label>
            <div class="flex items-center gap-2">
              <input type="color" value="{{ old($name, $value) }}"
                     class="w-10 h-10 rounded-lg border cursor-pointer"
                     style="border-color:var(--ui-border);padding:2px;">
              <input type="text" name="{{ $name }}" value="{{ old($name, $value) }}"
                     class="flex-1 px-2 py-1.5 rounded-lg border text-xs font-mono"
                     style="border-color:var(--ui-border);background:var(--ui-surface-soft);color:var(--ui-text-primary);"
                     pattern="^#[0-9a-fA-F]{6}$" oninput="syncColor(this)">
            </div>
          </div>
          @endforeach
        </div>
      </div>

      {{-- Submit --}}
      <div class="flex items-center justify-between">
        <a href="{{ route('admin.theme-manager.index') }}"
           class="px-6 py-2.5 rounded-lg border text-sm font-medium transition-colors"
           style="border-color:var(--ui-border);color:var(--ui-text-muted);background:var(--ui-surface);cursor:pointer;"
           onmouseover="this.style.background='var(--ui-surface-muted)'"
           onmouseout="this.style.background='var(--ui-surface)'">انصراف</a>
        <button type="submit"
                class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors"
                style="background:var(--ui-indigo);cursor:pointer;"
                onmouseover="this.style.background='var(--ui-indigo-hover)'"
                onmouseout="this.style.background='var(--ui-indigo)'">
          {{ $isNew ? 'ایجاد تم' : 'ذخیره تغییرات' }}
        </button>
      </div>
    </form>

  </div>
</div>

<script>
// Color picker ↔ text input sync (bi-directional)
document.querySelectorAll('input[type=color]').forEach(picker => {
  const text = picker.nextElementSibling;
  if (!text || text.type !== 'text') return;
  // picker → text
  picker.addEventListener('input', () => {
    text.value = picker.value;
    // Live preview the change
    previewSingleToken(text.name, picker.value);
  });
  // text → picker (on valid hex)
  text.addEventListener('input', () => {
    if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
      picker.value = text.value;
    }
  });
});

function previewSingleToken(fieldName, hex) {
  // Map form field names to CSS vars for instant preview
  const map = {
    'colors[primary][500]': '--ui-primary-500',
    'colors[accent][base]': '--ui-indigo',
    'colors[secondary][base]': '--ui-purple',
    'colors[success][base]': '--ui-success',
    'colors[warning][base]': '--ui-warning',
    'colors[danger][base]': '--ui-danger',
    'colors[info][base]': '--ui-info',
    'surfaces[bg]': '--ui-bg',
    'surfaces[surface]': '--ui-surface',
    'sidebar[bg]': '--platform-sidebar-bg-top',
    'sidebar[bg_bottom]': '--platform-sidebar-bg-bottom',
    'sidebar[text]': '--platform-sidebar-text',
    'sidebar[accent]': '--platform-sidebar-accent',
    'header[bg]': '--ds-menu-bg',
    'header[link_active]': '--ds-menu-link-active',
    'typography[text_primary]': '--ui-text-primary',
    'typography[link]': '--ui-indigo',
  };
  const cssVar = map[fieldName];
  if (cssVar) {
    document.documentElement.style.setProperty(cssVar, hex);
    const sidebar = document.getElementById('admin-sidebar');
    if (sidebar && ['--platform-sidebar-bg-top','--platform-sidebar-bg-bottom','--platform-sidebar-text','--platform-sidebar-accent'].includes(cssVar)) {
      sidebar.style.setProperty(cssVar, hex);
    }
  }
}
</script>
@endsection
