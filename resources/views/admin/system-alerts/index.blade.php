@extends('layouts.admin')
@section('title', 'اعلان‌های سیستمی')
@section('header', 'اعلان‌های سیستمی')
@push('head')
<style>
.notif-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;transition:all .15s;margin-bottom:8px}
.notif-card:hover{border-color:#c7d2fe;box-shadow:0 4px 16px rgba(99,102,241,.08)}
.notif-card.unread{border-right:3px solid #6366f1;background:#fafaff}
.notif-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px}
.notif-icon-info{background:#eff6ff}
.notif-icon-success{background:#f0fdf4}
.notif-icon-warning{background:#fffbeb}
.notif-icon-error{background:#fef2f2}
.notif-title{font-size:13.5px;font-weight:700;color:#1e293b;margin-bottom:3px;line-height:1.4}
.notif-body{font-size:12px;color:#64748b;line-height:1.5}
.notif-meta{font-size:11px;color:#94a3b8;margin-top:4px;display:flex;align-items:center;gap:8px}
.notif-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700}
.badge-info{background:#eff6ff;color:#1d4ed8}
.badge-success{background:#f0fdf4;color:#15803d}
.badge-warning{background:#fffbeb;color:#b45309}
.badge-error{background:#fef2f2;color:#b91c1c}
.notif-actions{display:flex;gap:6px;align-items:center;flex-shrink:0}
.mark-read-btn{padding:4px 10px;border-radius:7px;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit}
.mark-read-btn:hover{border-color:#6366f1;color:#6366f1;background:#eef2ff}
.del-btn{padding:4px 10px;border-radius:7px;border:1px solid #fecaca;background:#fff;color:#ef4444;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit}
.del-btn:hover{background:#fee2e2}
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8}
.empty-state svg{margin:0 auto 16px;display:block;opacity:.35}
</style>
@endpush
@section('content')

@include('admin.partials.breadcrumb', ['crumbs' => [
    ['label' => 'داشبورد', 'url' => route('admin.dashboard')],
    ['label' => 'اعلان‌های سیستمی', 'url' => null],
]])

<div class="max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900">اعلان‌های سیستمی</h2>
            <p class="text-sm text-gray-500 mt-0.5">رویدادهای مهم پلتفرم</p>
        </div>
        @if($notifications->total() > 0)
        <form method="POST" action="{{ route('admin.system-alerts.read-all') }}" id="read-all-form">
            @csrf
            <button type="submit"
                    onclick="return confirm('همه اعلان‌ها به عنوان خوانده‌شده علامت‌گذاری شوند؟')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                خواندن همه
            </button>
        </form>
        @endif
    </div>

    {{-- Notifications --}}
    @forelse($notifications as $n)
    @php
        $icons = [
            'info'    => ['emoji' => '📢', 'css' => 'notif-icon-info',    'badge' => 'badge-info'],
            'success' => ['emoji' => '✅', 'css' => 'notif-icon-success', 'badge' => 'badge-success'],
            'warning' => ['emoji' => '⚠️', 'css' => 'notif-icon-warning', 'badge' => 'badge-warning'],
            'error'   => ['emoji' => '🚨', 'css' => 'notif-icon-error',   'badge' => 'badge-error'],
        ];
        $ic = $icons[$n->type] ?? $icons['info'];
        $link = $n->data['link'] ?? null;
    @endphp
    <div class="notif-card {{ $n->isUnread() ? 'unread' : '' }}" id="notif-{{ $n->id }}">
        <div class="notif-icon {{ $ic['css'] }}">{{ $ic['emoji'] }}</div>
        <div class="flex-1 min-w-0">
            <div class="notif-title">{{ $n->title }}</div>
            @if($n->body)
            <div class="notif-body">{{ $n->body }}</div>
            @endif
            <div class="notif-meta">
                <span class="notif-badge {{ $ic['badge'] }}">{{ $n->type }}</span>
                <span>{{ $n->created_at->diffForHumans() }}</span>
                @if($n->isRead())
                <span class="text-emerald-500 font-medium">✓ خوانده شد</span>
                @endif
                @if($link)
                <a href="{{ $link }}" class="text-indigo-600 hover:underline font-medium text-xs">مشاهده →</a>
                @endif
            </div>
        </div>
        <div class="notif-actions">
            @if($n->isUnread())
            <button onclick="markRead({{ $n->id }}, this)" class="mark-read-btn">خواندم</button>
            @endif
            <form method="POST" action="{{ route('admin.system-alerts.destroy', $n->id) }}" onsubmit="return confirm('حذف شود؟')">
                @csrf @method('DELETE')
                <button type="submit" class="del-btn">حذف</button>
            </form>
        </div>
    </div>
    @empty
    <div class="empty-state">
        <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <p class="text-lg font-semibold text-gray-400 mb-1">هیچ اعلانی ندارید</p>
        <p class="text-sm text-gray-400">رویدادهای پلتفرم در اینجا نمایش داده می‌شوند.</p>
    </div>
    @endforelse

    {{-- Pagination --}}
    @if($notifications->hasPages())
    <div class="mt-6">{{ $notifications->links() }}</div>
    @endif

</div>

<script>
function markRead(id, btn) {
    fetch('/admin/system-alerts/' + id + '/read', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}
    }).then(() => {
        const card = document.getElementById('notif-' + id);
        if (card) { card.classList.remove('unread'); btn.remove(); }
    });
}
</script>
@endsection
