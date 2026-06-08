@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'بررسی محتوای هوش مصنوعی')

@section('content')
<div class="space-y-5" dir="rtl" x-data="bulkReview()">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">بررسی محتوای تولیدشده توسط هوش مصنوعی</h1>
            <p class="text-sm text-gray-500">تأیید یا رد مقالاتی که به‌صورت خودکار تولید شده‌اند</p>
        </div>
        <a href="{{ route('admin.education.articles.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            بازگشت به مقالات
        </a>
    </div>

    {{-- ── Analytics bar ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">در انتظار بررسی</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ number_format($analytics['pending_count']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">تأییدشده</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($analytics['approved_count']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">ردشده</p>
            <p class="mt-1 text-2xl font-bold text-rose-600">{{ number_format($analytics['rejected_count']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500">میانگین زمان بررسی</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">
                @if($analytics['avg_review_hours'] !== null)
                    {{ number_format($analytics['avg_review_hours'], 1) }} ساعت
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="search" name="search" value="{{ request('search') }}"
               placeholder="جستجو در عنوان مقالات…"
               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-cyan-400 focus:outline-none w-64">

        <select name="module_slug" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-cyan-400 focus:outline-none">
            <option value="">همه ماژول‌ها</option>
            @foreach($pending->pluck('module_slug')->unique()->sort() as $slug)
                <option value="{{ $slug }}" {{ request('module_slug') === $slug ? 'selected' : '' }}>{{ $slug }}</option>
            @endforeach
        </select>

        <select name="content_type" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-cyan-400 focus:outline-none">
            <option value="">همه انواع</option>
            <option value="articles"      {{ request('content_type') === 'articles'      ? 'selected' : '' }}>مقاله</option>
            <option value="walkthroughs"  {{ request('content_type') === 'walkthroughs'  ? 'selected' : '' }}>راهنمای گام‌به‌گام</option>
            <option value="learning_path" {{ request('content_type') === 'learning_path' ? 'selected' : '' }}>مسیر یادگیری</option>
        </select>

        <button type="submit"
                class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">
            اعمال فیلتر
        </button>
        <a href="{{ route('admin.education.generated-content.review') }}"
           class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            پاک‌کردن
        </a>
    </form>

    {{-- ── Bulk action toolbar ──────────────────────────────────────────────── --}}
    <div x-show="selected.length > 0" x-cloak
         class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
        <span class="text-sm font-medium text-emerald-800">
            <span x-text="selected.length"></span> مورد انتخاب شده
        </span>
        <button x-on:click="bulkApprove()"
                x-bind:disabled="bulkLoading"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            تأیید گروهی
        </button>
        <button x-on:click="selected = []"
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-white transition-colors">
            لغو انتخاب
        </button>
        <span x-show="bulkFeedback" x-cloak
              x-bind:class="bulkOk ? 'text-emerald-700' : 'text-rose-600'"
              class="text-sm font-medium" x-text="bulkFeedback"></span>
    </div>

    {{-- ── Queue table ──────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox"
                               x-on:change="toggleAll($event.target.checked)"
                               class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                    </th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">عنوان مقاله</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">ماژول</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">نوع</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">تاریخ تولید</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">اطمینان AI</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($pending as $article)
                <tr x-data="reviewRow({{ $article->id }})"
                    x-bind:class="removed ? 'hidden' : ''"
                    class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 w-10">
                        <input type="checkbox"
                               x-bind:value="{{ $article->id }}"
                               x-model="$root.selected"
                               class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.education.articles.show', $article) }}"
                           class="font-medium text-gray-900 hover:text-cyan-600 hover:underline line-clamp-2">
                            {{ $article->title }}
                        </a>
                        @if($article->excerpt)
                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $article->excerpt }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                            {{ $article->module_slug ?? '—' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $typeLabels = [
                                'articles'      => 'مقاله',
                                'walkthroughs'  => 'راهنما',
                                'learning_path' => 'مسیر یادگیری',
                            ];
                        @endphp
                        <span class="text-gray-600">{{ $typeLabels[$article->content_type] ?? $article->content_type }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $article->created_at->diffForHumans() }}
                    </td>
                    <td class="px-4 py-3">
                        @php $score = $article->meta['confidence_score'] ?? null; @endphp
                        @if($score !== null)
                            <div class="flex items-center gap-1.5">
                                <div class="h-1.5 w-20 rounded-full bg-gray-200 overflow-hidden">
                                    <div class="h-full rounded-full {{ $score >= 0.8 ? 'bg-emerald-500' : ($score >= 0.5 ? 'bg-amber-400' : 'bg-rose-400') }}"
                                         style="width: {{ round($score * 100) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ round($score * 100) }}٪</span>
                            </div>
                        @else
                            <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 justify-end">
                            {{-- Approve --}}
                            <button x-on:click="approve()"
                                    x-bind:disabled="loading"
                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-60 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                تأیید
                            </button>

                            {{-- Reject trigger --}}
                            <button x-on:click="showReject = true"
                                    x-bind:disabled="loading"
                                    class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-60 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                رد
                            </button>
                        </div>

                        {{-- Inline reject note form --}}
                        <div x-show="showReject" x-cloak class="mt-2 space-y-1">
                            <textarea x-model="rejectNote"
                                      rows="2"
                                      placeholder="دلیل رد مقاله را بنویسید… (حداقل ۵ کاراکتر)"
                                      class="block w-full rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-800 placeholder-gray-400 focus:border-rose-400 focus:outline-none resize-none"></textarea>
                            <div class="flex gap-1.5 justify-end">
                                <button x-on:click="showReject = false; rejectNote = ''"
                                        class="rounded-lg border border-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50">
                                    انصراف
                                </button>
                                <button x-on:click="reject()"
                                        x-bind:disabled="loading || rejectNote.trim().length < 5"
                                        class="rounded-lg bg-rose-600 px-3 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-60">
                                    تأیید رد
                                </button>
                            </div>
                        </div>

                        {{-- Feedback toast --}}
                        <div x-show="feedback" x-cloak
                             x-bind:class="feedbackOk ? 'text-emerald-600' : 'text-rose-600'"
                             class="mt-1 text-xs font-medium" x-text="feedback"></div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center gap-3 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-medium">هیچ مقاله‌ای در انتظار بررسی نیست</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Pagination ───────────────────────────────────────────────────────── --}}
    @if($pending->hasPages())
    <div class="flex justify-center">
        {{ $pending->withQueryString()->links() }}
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function bulkReview() {
    return {
        selected:      [],
        bulkLoading:   false,
        bulkFeedback:  '',
        bulkOk:        true,

        toggleAll(checked) {
            if (checked) {
                this.selected = Array.from(
                    document.querySelectorAll('input[type=checkbox][x-bind\\:value]')
                ).map(el => parseInt(el.value));
            } else {
                this.selected = [];
            }
        },

        async bulkApprove() {
            if (! this.selected.length) return;
            this.bulkLoading  = true;
            this.bulkFeedback = '';
            try {
                const res  = await fetch('{{ route('admin.education.generated-content.bulk-approve') }}', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF(),
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ ids: this.selected }),
                });
                const json = await res.json();
                if (res.ok) {
                    this.bulkFeedback = json.message;
                    this.bulkOk       = true;
                    // hide approved rows
                    this.selected.forEach(id => {
                        const cb = document.querySelector(`input[x-bind\\:value="${id}"]`);
                        cb?.closest('tr')?.classList.add('hidden');
                    });
                    this.selected = [];
                } else {
                    this.bulkFeedback = json.message ?? 'خطا در تأیید گروهی';
                    this.bulkOk       = false;
                }
            } catch {
                this.bulkFeedback = 'خطای شبکه';
                this.bulkOk       = false;
            }
            this.bulkLoading = false;
        },
    };
}

function reviewRow(articleId) {
    return {
        loading:     false,
        removed:     false,
        showReject:  false,
        rejectNote:  '',
        feedback:    '',
        feedbackOk:  true,

        async approve() {
            this.loading  = true;
            this.feedback = '';
            try {
                const res  = await fetch(`/admin/education/generated-content/${articleId}/approve`, {
                    method:  'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF(),
                        'Accept':       'application/json',
                    },
                });
                const json = await res.json();
                if (res.ok) {
                    this.feedback   = 'تأییدشد — منتشر گردید';
                    this.feedbackOk = true;
                    setTimeout(() => { this.removed = true; }, 900);
                } else {
                    this.feedback   = json.message ?? 'خطا در تأیید';
                    this.feedbackOk = false;
                }
            } catch {
                this.feedback   = 'خطای شبکه';
                this.feedbackOk = false;
            }
            this.loading = false;
        },

        async reject() {
            if (this.rejectNote.trim().length < 5) return;
            this.loading  = true;
            this.feedback = '';
            try {
                const res  = await fetch(`/admin/education/generated-content/${articleId}/reject`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF(),
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ review_note: this.rejectNote }),
                });
                const json = await res.json();
                if (res.ok) {
                    this.feedback   = 'ردشد — بایگانی گردید';
                    this.feedbackOk = true;
                    this.showReject = false;
                    setTimeout(() => { this.removed = true; }, 900);
                } else {
                    this.feedback   = json.message ?? 'خطا در رد';
                    this.feedbackOk = false;
                }
            } catch {
                this.feedback   = 'خطای شبکه';
                this.feedbackOk = false;
            }
            this.loading = false;
        },
    };
}
</script>
@endpush
