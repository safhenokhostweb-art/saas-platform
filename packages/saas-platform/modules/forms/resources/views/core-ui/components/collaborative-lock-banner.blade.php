@props([
    'resource'    => '',
    'resourceId'  => '',
    'currentUser' => null,
    'lock'        => null,  // ['user_id','locked_at','expires_at'] or null
    'lockedByName'=> 'کاربر دیگری',
    'canOverride' => false,
    'overrideUrl' => '#',
])
@php
$isLockedByOther = $lock && (string)($lock['user_id'] ?? '') !== (string)$currentUser;
@endphp

<div x-data="collaborativeLock('{{ $resource }}', '{{ $resourceId }}', '{{ $currentUser }}')"
     x-init="init()"
     role="status"
     aria-live="polite">

    {{-- Locked by another user --}}
    <template x-if="isLockedByOther">
        <div class="flex items-start gap-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm mb-3 shadow-sm">
            <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <div class="flex-1 min-w-0">
                <p class="font-semibold">این بخش در حال ویرایش توسط <span x-text="lockHolder"></span> است</p>
                <p class="text-xs text-amber-600 mt-0.5">تغییرات شما ممکن است بازنویسی شود. صبر کنید یا پس از آزاد شدن ویرایش کنید.</p>
            </div>
            @if($canOverride)
            <button @click="forceUnlock()"
                    class="shrink-0 text-xs font-semibold text-amber-700 underline hover:no-underline">
                لغو قفل
            </button>
            @endif
        </div>
    </template>

    {{-- Locked by current user --}}
    <template x-if="isLockedBySelf">
        <div class="flex items-center gap-2 p-2 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs mb-2">
            <svg class="w-3.5 h-3.5 shrink-0 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            <span>این بخش توسط شما قفل شده</span>
            <button @click="releaseLock()" class="mr-auto text-indigo-500 hover:text-indigo-700">رها کردن</button>
        </div>
    </template>

    {{-- Viewers --}}
    <template x-if="viewers.length > 1">
        <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
            </svg>
            <span x-text="`${viewers.length} نفر در حال مشاهده`"></span>
        </div>
    </template>
</div>

@push('scripts')
<script>
function collaborativeLock(resource, resourceId, currentUserId) {
    return {
        lockHolder: '{{ $lockedByName }}',
        lockUserId: @json($lock ? ($lock['user_id'] ?? null) : null),
        viewers: [],
        get isLockedByOther() { return this.lockUserId && String(this.lockUserId) !== String(currentUserId); },
        get isLockedBySelf()  { return this.lockUserId && String(this.lockUserId) === String(currentUserId); },

        async fetchLock() {
            try {
                const r = await fetch(`/api/collab/lock?resource=${resource}&resource_id=${resourceId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) {
                    const d = await r.json();
                    this.lockUserId = d.user_id ?? null;
                    this.lockHolder = d.user_name ?? 'کاربر دیگری';
                    this.viewers    = d.viewers ?? [];
                }
            } catch {}
        },

        async releaseLock() {
            try {
                await fetch(`/api/collab/lock/release`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                    body: JSON.stringify({ resource, resource_id: resourceId })
                });
                this.lockUserId = null;
            } catch {}
        },

        async forceUnlock() {
            if (!confirm('آیا مطمئنید؟ ویرایش‌های ذخیره‌نشده کاربر دیگر از دست می‌رود.')) return;
            await this.releaseLock();
        },

        init() {
            this.fetchLock();
            setInterval(() => this.fetchLock(), 20000);
        }
    };
}
</script>
@endpush
