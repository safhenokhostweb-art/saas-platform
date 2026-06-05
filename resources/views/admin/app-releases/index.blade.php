@extends('layouts.admin')

@section('header', 'دانلود اپلیکیشن')

@push('head')
<style>
.platform-card { transition: all 0.2s ease; }
.platform-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,102,241,0.12); }
.release-row:hover { background: rgba(99,102,241,0.03); }
</style>
@endpush

@section('content')

{{-- ── آمار بالا ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-4 mb-7">
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center text-xl">📦</div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</p>
            <p class="text-sm text-gray-500">کل نسخه‌ها</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center text-xl">⬇️</div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['downloads']) }}</p>
            <p class="text-sm text-gray-500">کل دانلودها</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-purple-50 flex items-center justify-center text-xl">🖥️</div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $stats['platforms'] }}</p>
            <p class="text-sm text-gray-500">پلتفرم فعال</p>
        </div>
    </div>
</div>

{{-- ── کارت‌های پلتفرم (آخرین نسخه) ──────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    @php
        $platformDefs = [
            'android' => ['label' => 'Android', 'icon' => '🤖', 'ext' => 'APK', 'color' => 'green',  'desc' => 'گوشی‌های اندروید'],
            'windows' => ['label' => 'Windows', 'icon' => '🪟', 'ext' => 'EXE', 'color' => 'blue',   'desc' => 'ویندوز ۱۰ و بالاتر'],
            'macos'   => ['label' => 'macOS',   'icon' => '🍎', 'ext' => 'DMG', 'color' => 'gray',   'desc' => 'macOS 13+'],
            'linux'   => ['label' => 'Linux',   'icon' => '🐧', 'ext' => 'AppImage', 'color' => 'orange', 'desc' => 'Ubuntu / Debian'],
        ];
        $colorMap = [
            'green'  => ['bg' => 'bg-green-50',  'border' => 'border-green-200',  'badge' => 'bg-green-100 text-green-700',  'btn' => 'bg-green-600 hover:bg-green-700'],
            'blue'   => ['bg' => 'bg-blue-50',   'border' => 'border-blue-200',   'badge' => 'bg-blue-100 text-blue-700',   'btn' => 'bg-blue-600 hover:bg-blue-700'],
            'gray'   => ['bg' => 'bg-gray-50',   'border' => 'border-gray-200',   'badge' => 'bg-gray-200 text-gray-700',   'btn' => 'bg-gray-700 hover:bg-gray-800'],
            'orange' => ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'badge' => 'bg-orange-100 text-orange-700', 'btn' => 'bg-orange-600 hover:bg-orange-700'],
        ];
    @endphp

    @foreach($platformDefs as $key => $def)
    @php
        $latest = $latestByPlatform[$key] ?? null;
        $c = $colorMap[$def['color']];
    @endphp
    <div class="platform-card bg-white rounded-2xl border {{ $c['border'] }} p-5 flex flex-col gap-3">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <span class="text-3xl">{{ $def['icon'] }}</span>
                <div>
                    <p class="font-bold text-gray-800">{{ $def['label'] }}</p>
                    <p class="text-xs text-gray-400">{{ $def['desc'] }}</p>
                </div>
            </div>
            @if($latest)
                <span class="text-xs px-2 py-1 rounded-full {{ $c['badge'] }} font-semibold">v{{ $latest->version }}</span>
            @else
                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-400">—</span>
            @endif
        </div>

        @if($latest)
            <div class="text-xs text-gray-500 space-y-1">
                <div class="flex justify-between"><span>حجم فایل:</span><span class="font-medium text-gray-700">{{ $latest->size_label }}</span></div>
                <div class="flex justify-between"><span>دانلود:</span><span class="font-medium text-gray-700">{{ number_format($latest->download_count) }} بار</span></div>
                <div class="flex justify-between"><span>آپلود:</span><span class="font-medium text-gray-700">{{ $latest->created_at->diffForHumans() }}</span></div>
            </div>
            <a href="{{ route('admin.app-releases.download', $latest) }}"
               class="{{ $c['btn'] }} text-white text-sm font-medium py-2 px-4 rounded-lg flex items-center justify-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                دانلود {{ $def['ext'] }}
            </a>
        @else
            <div class="text-center py-4 text-gray-400 text-sm">هنوز نسخه‌ای آپلود نشده</div>
            <button onclick="document.getElementById('upload-modal').classList.remove('hidden'); document.getElementById('upload-platform').value='{{ $key }}'"
                    class="border border-dashed border-gray-300 text-gray-500 hover:border-indigo-400 hover:text-indigo-600 text-sm py-2 px-4 rounded-lg flex items-center justify-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                آپلود اولین نسخه
            </button>
        @endif
    </div>
    @endforeach
</div>

{{-- ── جدول تمام نسخه‌ها ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between p-5 border-b border-gray-100">
        <h2 class="text-base font-bold text-gray-800">تاریخچه نسخه‌ها</h2>
        <button onclick="document.getElementById('upload-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            آپلود نسخه جدید
        </button>
    </div>

    @if($releases->isEmpty())
        <div class="py-16 text-center">
            <div class="text-5xl mb-4">📱</div>
            <p class="text-gray-500">هنوز هیچ نسخه‌ای آپلود نشده است.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-right text-gray-500 text-xs">
                    <tr>
                        <th class="px-5 py-3 font-medium">پلتفرم</th>
                        <th class="px-5 py-3 font-medium">نسخه</th>
                        <th class="px-5 py-3 font-medium">فایل</th>
                        <th class="px-5 py-3 font-medium">حجم</th>
                        <th class="px-5 py-3 font-medium">دانلود</th>
                        <th class="px-5 py-3 font-medium">آپلود توسط</th>
                        <th class="px-5 py-3 font-medium">تاریخ</th>
                        <th class="px-5 py-3 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($releases->flatten() as $release)
                    <tr class="release-row transition-colors">
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="text-base">{{ $release->platform_icon }}</span>
                                <span class="font-medium text-gray-700">{{ $release->platform_label }}</span>
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-mono text-gray-800">v{{ $release->version }}</span>
                            <span class="text-gray-400 text-xs mr-1">({{ $release->build_number }})</span>
                            @if($release->is_latest)
                                <span class="mr-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">جدیدترین</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-500 max-w-xs truncate">{{ $release->file_name }}</td>
                        <td class="px-5 py-3 text-gray-600 whitespace-nowrap">{{ $release->size_label }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-1 text-gray-600">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                {{ number_format($release->download_count) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $release->uploader?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">{{ $release->created_at->diffForHumans() }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                {{-- دانلود --}}
                                <a href="{{ route('admin.app-releases.download', $release) }}"
                                   class="text-indigo-600 hover:text-indigo-800 p-1 rounded hover:bg-indigo-50" title="دانلود">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                                {{-- جدیدترین --}}
                                @if(!$release->is_latest)
                                <form method="POST" action="{{ route('admin.app-releases.set-latest', $release) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50 text-xs" title="تعیین به عنوان جدیدترین">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                                @endif
                                {{-- یادداشت --}}
                                @if($release->release_notes)
                                <button title="{{ $release->release_notes }}"
                                        class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-50"
                                        onclick="alert('{{ addslashes($release->release_notes) }}')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                @endif
                                {{-- حذف --}}
                                <form method="POST" action="{{ route('admin.app-releases.destroy', $release) }}"
                                      class="inline" onsubmit="return confirm('این نسخه حذف شود؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50" title="حذف">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ── Modal آپلود ──────────────────────────────────────────────────────── --}}
<div id="upload-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800">آپلود نسخه جدید</h3>
            <button onclick="document.getElementById('upload-modal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.app-releases.store') }}" enctype="multipart/form-data"
              class="p-6 space-y-4">
            @csrf

            @if($errors->any())
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
            @endif

            {{-- پلتفرم --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">پلتفرم *</label>
                <select id="upload-platform" name="platform" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">انتخاب پلتفرم...</option>
                    <option value="android">🤖 Android (APK)</option>
                    <option value="windows">🪟 Windows (EXE / ZIP)</option>
                    <option value="macos">🍎 macOS (DMG / ZIP)</option>
                    <option value="linux">🐧 Linux (AppImage)</option>
                </select>
            </div>

            {{-- نسخه + build --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">شماره نسخه *</label>
                    <input type="text" name="version" value="{{ old('version', '1.0.0') }}" required
                           pattern="\d+\.\d+\.\d+" placeholder="1.0.0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Build Number *</label>
                    <input type="number" name="build_number" value="{{ old('build_number', 1) }}" required min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
            </div>

            {{-- فایل --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">فایل *</label>
                <input type="file" name="file" required accept=".apk,.exe,.zip,.dmg,.AppImage,.msi"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700">
                <p class="text-xs text-gray-400 mt-1">حداکثر ۲۰۰ MB — فرمت‌های مجاز: APK, EXE, ZIP, DMG, AppImage</p>
            </div>

            {{-- یادداشت --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">یادداشت نسخه (Release Notes)</label>
                <textarea name="release_notes" rows="3" placeholder="تغییرات این نسخه را بنویسید... (اختیاری)"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('release_notes', '') }}</textarea>
            </div>

            {{-- جدیدترین --}}
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="set_latest" value="1" checked
                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">این نسخه را به عنوان <strong>جدیدترین نسخه</strong> تعیین کن</span>
            </label>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('upload-modal').classList.add('hidden')"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    انصراف
                </button>
                <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    آپلود
                </button>
            </div>
        </form>
    </div>
</div>

{{-- در صورت خطا validate، modal را باز نگه‌دار --}}
@if($errors->any())
<script>document.getElementById('upload-modal').classList.remove('hidden');</script>
@endif

@endsection
