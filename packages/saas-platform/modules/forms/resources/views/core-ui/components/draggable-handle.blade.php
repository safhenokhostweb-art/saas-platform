{{-- Drag handle for SortableJS. Place inside draggable element. --}}
<span {{ $attributes->merge(['class' => 'cursor-grab active:cursor-grabbing text-slate-300 hover:text-slate-500 transition shrink-0 select-none']) }}
      title="برای تغییر ترتیب بکشید">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
        <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
        <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
    </svg>
</span>
