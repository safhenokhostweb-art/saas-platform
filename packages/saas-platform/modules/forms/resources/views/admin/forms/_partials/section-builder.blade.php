{{--
  Advanced Section Builder Partial — injected into form builder canvas.
  Provides: collapsible sections, multi-column layouts, visual grouping,
  reusable section templates, drag-and-drop between sections.
--}}

{{-- Section Templates Modal --}}
<div x-show="showSectionTemplates"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @click.self="showSectionTemplates = false">
    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showSectionTemplates = false"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 z-10">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900">قالب‌های بخش آماده</h3>
            <button @click="showSectionTemplates = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <template x-for="tpl in sectionTemplates" :key="tpl.id">
                <div @click="addSectionFromTemplate(tpl); showSectionTemplates = false"
                     class="p-4 border border-gray-200 rounded-xl hover:border-indigo-400 hover:bg-indigo-50 cursor-pointer transition group">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 group-hover:bg-indigo-100 flex items-center justify-center text-lg mb-2">
                        <span x-text="tpl.icon"></span>
                    </div>
                    <p class="font-semibold text-sm text-gray-800" x-text="tpl.name"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="tpl.description"></p>
                </div>
            </template>
        </div>
    </div>
</div>

{{-- Undo/Redo Toolbar (injected into builder toolbar) --}}
<div class="flex items-center gap-1 border-r border-slate-200 pr-3 mr-1">
    <button @click="undo()"
            :disabled="historyIndex <= 0"
            :class="historyIndex <= 0 ? 'opacity-30 cursor-not-allowed' : 'hover:bg-slate-100'"
            class="p-1.5 rounded-lg transition text-slate-600"
            title="برگشت (Ctrl+Z)">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
        </svg>
    </button>
    <button @click="redo()"
            :disabled="historyIndex >= history.length - 1"
            :class="historyIndex >= history.length - 1 ? 'opacity-30 cursor-not-allowed' : 'hover:bg-slate-100'"
            class="p-1.5 rounded-lg transition text-slate-600"
            title="جلو (Ctrl+Y)">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/>
        </svg>
    </button>
    <span class="text-xs text-slate-400 px-1" x-text="`${historyIndex + 1}/${history.length}`"></span>
</div>

{{-- Mobile Preview Toggle --}}
<button @click="previewMode = previewMode === 'desktop' ? 'mobile' : 'desktop'"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition"
        :class="previewMode === 'mobile' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
        title="پیش‌نمایش موبایل">
    <template x-if="previewMode === 'desktop'">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3"/>
        </svg>
    </template>
    <template x-if="previewMode === 'mobile'">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
        </svg>
    </template>
    <span x-text="previewMode === 'desktop' ? 'موبایل' : 'دسکتاپ'"></span>
</button>

{{-- AI Field Suggestions Button --}}
<button @click="fetchAiSuggestions()"
        :disabled="aiLoading"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-gradient-to-r from-violet-500 to-purple-600 text-white hover:opacity-90 transition disabled:opacity-50"
        title="پیشنهاد هوشمند فیلد">
    <svg class="w-4 h-4" :class="aiLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
    </svg>
    <span x-text="aiLoading ? 'در حال تحلیل...' : 'پیشنهاد AI'"></span>
</button>

{{-- AI Suggestions Panel --}}
<template x-if="aiSuggestions.length > 0">
    <div class="absolute top-16 left-4 z-20 w-72 bg-white rounded-2xl shadow-2xl border border-indigo-100 overflow-hidden">
        <div class="p-3 border-b border-indigo-50 bg-gradient-to-r from-violet-50 to-purple-50 flex items-center justify-between">
            <p class="text-xs font-bold text-violet-700 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                </svg>
                پیشنهادهای AI
            </p>
            <button @click="aiSuggestions = []" class="text-gray-400 hover:text-gray-600 text-xs">بستن ✕</button>
        </div>
        <div class="p-2 max-h-64 overflow-y-auto">
            <template x-for="(s, i) in aiSuggestions" :key="i">
                <div @click="addAiField(s)"
                     class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-indigo-50 cursor-pointer transition group">
                    <span class="w-6 h-6 rounded bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 shrink-0" x-text="i + 1"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate" x-text="s.label"></p>
                        <p class="text-xs text-gray-400" x-text="s.type"></p>
                    </div>
                    <span class="text-indigo-500 opacity-0 group-hover:opacity-100 text-xs">+</span>
                </div>
            </template>
        </div>
        <div class="p-2 border-t border-gray-100">
            <button @click="addAllAiFields()" class="w-full text-center text-xs font-semibold text-indigo-600 hover:text-indigo-800 py-1 transition">
                افزودن همه فیلدها
            </button>
        </div>
    </div>
</template>

{{-- UX Issues Warning --}}
<template x-if="uxIssues.length > 0">
    <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
        <p class="text-xs font-bold text-amber-700 mb-1.5 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            مشکلات UX شناسایی شده
        </p>
        <template x-for="issue in uxIssues" :key="issue.type">
            <p class="text-xs text-amber-600" x-text="'• ' + issue.message"></p>
        </template>
    </div>
</template>

@push('scripts')
<script>
// Section Builder extensions — merged into the formBuilder() Alpine component via $data extend pattern
document.addEventListener('alpine:init', () => {
    // Extend formBuilder with section + advanced capabilities
    const _origFormBuilder = window.formBuilder ?? function(d) { return {}; };
    window.formBuilderSectionExt = {
        previewMode: 'desktop',
        showSectionTemplates: false,
        aiSuggestions: [],
        aiLoading: false,
        uxIssues: [],
        history: [],
        historyIndex: -1,
        maxHistory: 50,

        sectionTemplates: [
            { id: 'contact',   icon: '📞', name: 'اطلاعات تماس',  description: 'نام، ایمیل، تلفن', fields: [{type:'text',label:'نام'},{type:'email',label:'ایمیل'},{type:'text',label:'تلفن'}] },
            { id: 'address',   icon: '🏠', name: 'آدرس',          description: 'استان، شهر، کد پستی', fields: [{type:'text',label:'استان'},{type:'text',label:'شهر'},{type:'text',label:'کد پستی'}] },
            { id: 'feedback',  icon: '⭐', name: 'نظرسنجی',       description: 'امتیاز + توضیحات', fields: [{type:'rating',label:'امتیاز'},{type:'textarea',label:'نظر'}] },
            { id: 'identity',  icon: '🪪', name: 'هویت',          description: 'کد ملی، تاریخ تولد', fields: [{type:'text',label:'کد ملی'},{type:'text',label:'تاریخ تولد'}] },
        ],

        pushHistory() {
            this.history = this.history.slice(0, this.historyIndex + 1);
            this.history.push(JSON.parse(JSON.stringify(this.fields ?? [])));
            if (this.history.length > this.maxHistory) this.history.shift();
            this.historyIndex = this.history.length - 1;
        },

        undo() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.fields = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            }
        },

        redo() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.fields = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            }
        },

        addSectionFromTemplate(tpl) {
            const section = { type: 'section', label: tpl.name, id: 'section_' + Date.now(), collapsed: false, columns: 1, fields: [] };
            tpl.fields.forEach(f => {
                this.fields.push({ ...f, id: 'field_' + Date.now() + Math.random(), section: section.id });
            });
            this.pushHistory();
        },

        async fetchAiSuggestions() {
            this.aiLoading = true;
            try {
                const r = await fetch('/api/ai/form-field-suggestions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                    body: JSON.stringify({ title: this.form?.title ?? '', field_count: this.fields?.length ?? 0 })
                });
                if (r.ok) { const d = await r.json(); this.aiSuggestions = d.suggestions ?? []; }
            } catch {}
            this.aiLoading = false;
        },

        addAiField(suggestion) {
            this.addField(suggestion.type ?? 'text');
            if (this.fields.length > 0) {
                this.fields[this.fields.length - 1].label = suggestion.label ?? '';
                if (suggestion.required !== undefined) {
                    this.fields[this.fields.length - 1].required = suggestion.required;
                }
            }
            this.aiSuggestions = this.aiSuggestions.filter(s => s !== suggestion);
            this.pushHistory();
        },

        addAllAiFields() {
            this.aiSuggestions.forEach(s => this.addAiField(s));
            this.aiSuggestions = [];
        },

        detectUxIssues() {
            this.uxIssues = [];
            if ((this.fields?.length ?? 0) > 10) {
                this.uxIssues.push({ type: 'overload', message: 'فرم دارای بیش از ۱۰ فیلد است — بخش‌بندی توصیه می‌شود' });
            }
            const req = (this.fields ?? []).filter(f => f.required).length;
            if (req > (this.fields?.length ?? 0) * 0.7 && (this.fields?.length ?? 0) > 3) {
                this.uxIssues.push({ type: 'required_overload', message: 'بیش از ۷۰٪ فیلدها اجباری هستند' });
            }
        }
    };
});

// Keyboard shortcuts for undo/redo
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        const el = document.querySelector('[x-data]');
        if (el) window.Alpine.$data(el)?.undo?.();
    }
    if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) {
        e.preventDefault();
        const el = document.querySelector('[x-data]');
        if (el) window.Alpine.$data(el)?.redo?.();
    }
});
</script>
@endpush
