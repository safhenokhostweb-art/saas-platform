{{--
  Core-UI Page Header — Phase 9.2
  Usage:
    <x-core-ui::page-header title="فرم‌ها" description="مدیریت فرم‌های حرفه‌ای">
      <x-slot name="actions">
        <a href="..." class="...">فرم جدید</a>
      </x-slot>
    </x-core-ui::page-header>
  Props: title, description
--}}
@props([
    'title'       => '',
    'description' => '',
])

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div>
    @if($title)
      <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>
    @endif
    @if($description)
      <p class="text-sm text-slate-500 mt-0.5">{{ $description }}</p>
    @endif
  </div>
  @if(isset($actions))
    <div class="flex items-center gap-2">
      {{ $actions }}
    </div>
  @endif
</div>
