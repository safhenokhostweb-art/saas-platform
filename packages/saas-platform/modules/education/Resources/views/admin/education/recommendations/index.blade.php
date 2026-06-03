@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'توصیه‌های یادگیری')

@section('content')
<div class="space-y-5" dir="rtl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">توصیه‌های یادگیری</h1>
            <p class="text-sm text-gray-500 mt-0.5">توصیه‌های فعال بر اساس الگوی استفاده از پلتفرم</p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route('admin.education.recommendations.generate') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">
                    تولید توصیه‌های جدید
                </button>
            </form>
            <a href="{{ route('admin.education.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">بازگشت</a>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    @forelse($active as $rec)
    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm flex items-start justify-between gap-4">
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                    @if($rec->recommendation_type === 'feature_intro') bg-indigo-50 text-indigo-700
                    @elseif($rec->recommendation_type === 'article') bg-cyan-50 text-cyan-700
                    @elseif($rec->recommendation_type === 'learning_path') bg-emerald-50 text-emerald-700
                    @else bg-gray-50 text-gray-700 @endif">
                    {{ match($rec->recommendation_type) {
                        'feature_intro' => 'معرفی ویژگی',
                        'article'       => 'مقاله',
                        'learning_path' => 'مسیر یادگیری',
                        'walkthrough'   => 'راهنمای گام‌به‌گام',
                        default         => $rec->recommendation_type,
                    } }}
                </span>
                <span class="text-xs text-gray-400">اولویت: {{ $rec->priority }}</span>
            </div>

            @if($rec->article)
            <p class="font-medium text-gray-900">{{ $rec->article->title }}</p>
            @elseif($rec->learningPath)
            <p class="font-medium text-gray-900">{{ $rec->learningPath->title }}</p>
            @elseif($rec->walkthrough)
            <p class="font-medium text-gray-900">{{ $rec->walkthrough->title }}</p>
            @endif

            @if($rec->reason)
            <p class="mt-1 text-xs text-gray-500">{{ $rec->reason }}</p>
            @endif
        </div>

        <div class="flex gap-2 flex-shrink-0">
            <form action="{{ route('admin.education.recommendations.complete', $rec) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">تکمیل</button>
            </form>
            <form action="{{ route('admin.education.recommendations.dismiss', $rec) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-100">رد</button>
            </form>
        </div>
    </div>
    @empty
    <div class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center">
        <p class="text-gray-400 text-sm">توصیه فعالی وجود ندارد.</p>
        <p class="text-gray-400 text-xs mt-1">برای تولید توصیه‌های جدید، روی دکمه بالا کلیک کنید.</p>
    </div>
    @endforelse

</div>
@endsection
