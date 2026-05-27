{{--
    Session-driven toast strip. Place once in layout or page header.
    Supports: success, error, warning, info session messages.
    Also supports Alpine.js-driven toast via global window.showToast().
--}}
<div id="core-toast-container"
     class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"
     style="max-width: 380px">
</div>

@if(session('success') || session('error') || session('warning') || session('info'))
@push('scripts')
<script>
(function() {
    const messages = [
        @if(session('success'))  { type: 'success', text: @json(session('success')) }, @endif
        @if(session('error'))    { type: 'error',   text: @json(session('error')) }, @endif
        @if(session('warning'))  { type: 'warning', text: @json(session('warning')) }, @endif
        @if(session('info'))     { type: 'info',    text: @json(session('info')) }, @endif
    ];
    messages.forEach((m, i) => setTimeout(() => window.showToast && window.showToast(m.type, m.text), i * 100));
})();
</script>
@endpush
@endif

@push('scripts')
<script>
window.showToast = function(type, text, duration = 4000) {
    const container = document.getElementById('core-toast-container');
    if (!container) return;

    const colors = {
        success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        error:   'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-amber-50 border-amber-200 text-amber-800',
        info:    'bg-blue-50 border-blue-200 text-blue-800',
    };
    const icons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
        error:   '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>',
        warning: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>',
        info:    '<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z"/>',
    };

    const el = document.createElement('div');
    el.className = `pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium
        transition-all duration-300 opacity-0 translate-y-2 ${colors[type] || colors.info}`;
    el.innerHTML = `
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            ${icons[type] || icons.info}
        </svg>
        <span class="flex-1">${text}</span>
        <button onclick="this.closest('.pointer-events-auto').remove()" class="opacity-50 hover:opacity-100 transition shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    `;

    container.appendChild(el);
    requestAnimationFrame(() => {
        el.classList.remove('opacity-0', 'translate-y-2');
        el.classList.add('opacity-100', 'translate-y-0');
    });

    setTimeout(() => {
        el.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => el.remove(), 300);
    }, duration);
};
</script>
@endpush
