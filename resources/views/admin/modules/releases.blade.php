@extends('layouts.admin')

@section('title', 'انتشار نسخه ماژول | پنل مدیریت')
@section('header', 'انتشار نسخه ماژول')

@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-lg font-bold text-gray-900">{{ $module->title }} <span class="text-sm text-gray-500">({{ $module->slug }})</span></h2>
        <p class="text-sm text-gray-600 mt-1">نسخه فعلی: <strong>{{ $module->version ?? '1.0.0' }}</strong></p>
    </div>

    <form method="POST" action="{{ route('admin.modules.releases.store', $module) }}" class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
        @csrf
        <h3 class="font-semibold text-gray-900">ثبت انتشار جدید</h3>
        <p class="text-xs text-gray-500">
            برای انتشار حرفه‌ای، نسخه باید SemVer معتبر و بزرگ‌تر از نسخهٔ فعلی باشد و توضیحات تغییرات کامل ثبت شود.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="form-label text-xs">نسخه (SemVer)</label>
                <input name="version" class="form-control" placeholder="مثلاً 1.2.0" required>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                    <button type="button" class="px-2 py-1 rounded border border-gray-200 text-gray-700 js-semver-suggest" data-version="{{ $suggestedVersions['patch'] ?? '' }}">
                        patch: {{ $suggestedVersions['patch'] ?? '—' }}
                    </button>
                    <button type="button" class="px-2 py-1 rounded border border-gray-200 text-gray-700 js-semver-suggest" data-version="{{ $suggestedVersions['minor'] ?? '' }}">
                        minor: {{ $suggestedVersions['minor'] ?? '—' }}
                    </button>
                    <button type="button" class="px-2 py-1 rounded border border-gray-200 text-gray-700 js-semver-suggest" data-version="{{ $suggestedVersions['major'] ?? '' }}">
                        major: {{ $suggestedVersions['major'] ?? '—' }}
                    </button>
                </div>
            </div>
            <div>
                <label class="form-label text-xs">نوع انتشار</label>
                <select name="release_type" class="form-select" required>
                    <option value="patch">patch</option>
                    <option value="minor">minor</option>
                    <option value="major">major</option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" checked>
                    فعال
                </label>
            </div>
        </div>
        <div>
            <label class="form-label text-xs">تغییرات (changelog)</label>
            <textarea name="changelog" rows="5" class="form-control" placeholder="تغییرات این نسخه را بنویسید..." required>{{ old('changelog') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary">انتشار نسخه</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-sm">تاریخچه نسخه‌ها</div>
        <div class="divide-y">
            @forelse($releases as $release)
                <article class="p-4">
                    <div class="flex items-center justify-between text-sm">
                        <div class="font-semibold">v{{ $release->version }} <span class="text-xs text-gray-500">({{ $release->release_type }})</span></div>
                        <div class="text-xs text-gray-500">{{ $release->distributed_at ? \App\Helpers\Jalali::formatFa($release->distributed_at, 'Y/m/d H:i') : '—' }}</div>
                    </div>
                    @if($release->changelog)
                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-line">{{ $release->changelog }}</p>
                    @endif
                </article>
            @empty
                <p class="p-6 text-sm text-gray-500">هنوز نسخه‌ای برای این ماژول ثبت نشده است.</p>
            @endforelse
        </div>
        <div class="p-4 border-t">{{ $releases->links() }}</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const versionInput = document.querySelector('input[name="version"]');
        const releaseTypeInput = document.querySelector('select[name="release_type"]');
        if (!versionInput) return;

        document.querySelectorAll('.js-semver-suggest').forEach((btn) => {
            btn.addEventListener('click', function () {
                const v = this.dataset.version || '';
                if (!v) return;
                versionInput.value = v;
                const type = this.textContent.includes('major') ? 'major' : (this.textContent.includes('minor') ? 'minor' : 'patch');
                if (releaseTypeInput) {
                    releaseTypeInput.value = type;
                }
            });
        });
    })();
</script>
@endsection
