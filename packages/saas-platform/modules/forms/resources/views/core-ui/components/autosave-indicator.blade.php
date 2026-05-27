{{--
    Alpine.js autosave indicator.
    Expects x-data parent to have: saveStatus ('saved' | 'saving' | 'unsaved' | 'error')
--}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 text-xs select-none']) }}
     :class="{
       'text-slate-400': saveStatus === 'saved',
       'text-indigo-500': saveStatus === 'saving',
       'text-amber-500': saveStatus === 'unsaved',
       'text-red-500': saveStatus === 'error',
     }">
    <template x-if="saveStatus === 'saving'">
        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    </template>
    <template x-if="saveStatus === 'saved'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
        </svg>
    </template>
    <template x-if="saveStatus === 'unsaved'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
    </template>
    <template x-if="saveStatus === 'error'">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
        </svg>
    </template>
    <span x-text="{saved: 'ذخیره شد', saving: 'در حال ذخیره...', unsaved: 'تغییرات ذخیره نشده', error: 'خطا در ذخیره'}[saveStatus] || ''"></span>
</div>
