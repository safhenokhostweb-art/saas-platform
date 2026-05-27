@php
    $fieldName  = 'field_' . $field->id;
    $fieldId    = 'f-' . $field->id;
    $oldVal     = old($fieldName);
    $colClass   = match ($field->column_span ?? 12) {
        4  => 'md:col-span-4',
        6  => 'md:col-span-6',
        default => 'md:col-span-12',
    };
@endphp

@if($field->type === 'section_header')
<div class="{{ $colClass }} flex items-center gap-3 py-2">
    <div class="flex-1 h-px bg-slate-200"></div>
    <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">{{ $field->label }}</span>
    <div class="flex-1 h-px bg-slate-200"></div>
</div>
@else
<div class="{{ $colClass }}" x-data x-show="true">
    <label for="{{ $fieldId }}" class="form-label block mb-1.5 text-sm font-medium text-slate-700">
        {{ $field->label }}
        @if($field->is_required)
            <span class="text-red-500 mr-0.5" aria-hidden="true">*</span>
        @endif
    </label>

    @if(in_array($field->type, ['text', 'email', 'number', 'phone'], true))
        <input
            id="{{ $fieldId }}"
            type="{{ $field->type === 'phone' ? 'tel' : $field->type }}"
            name="{{ $fieldName }}"
            value="{{ $oldVal }}"
            class="form-input w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition bg-white placeholder-slate-400"
            placeholder="{{ $field->placeholder }}"
            @if($field->is_required) required @endif
            @if(($field->validation_rules['min_length'] ?? null)) minlength="{{ $field->validation_rules['min_length'] }}" @endif
            @if(($field->validation_rules['max_length'] ?? null)) maxlength="{{ $field->validation_rules['max_length'] }}" @endif
            @if($field->type === 'number' && ($field->validation_rules['min'] ?? null) !== null) min="{{ $field->validation_rules['min'] }}" @endif
            @if($field->type === 'number' && ($field->validation_rules['max'] ?? null) !== null) max="{{ $field->validation_rules['max'] }}" @endif
            aria-required="{{ $field->is_required ? 'true' : 'false' }}"
        >

    @elseif($field->type === 'textarea')
        <textarea
            id="{{ $fieldId }}"
            name="{{ $fieldName }}"
            rows="4"
            class="form-input w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition bg-white placeholder-slate-400 resize-y"
            placeholder="{{ $field->placeholder }}"
            @if($field->is_required) required @endif
            aria-required="{{ $field->is_required ? 'true' : 'false' }}"
        >{{ $oldVal }}</textarea>

    @elseif($field->type === 'date')
        <x-persian-date-input
            id="{{ $fieldId }}"
            name="{{ $fieldName }}"
            value="{{ $oldVal }}"
            placeholder="{{ $field->placeholder ?: '۱۴۰۴/۰۱/۱۵' }}"
            class="form-input w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition bg-white persian-date-input"
        />

    @elseif($field->type === 'select')
        <select
            id="{{ $fieldId }}"
            name="{{ $fieldName }}"
            class="form-input w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition bg-white"
            @if($field->is_required) required @endif
            aria-required="{{ $field->is_required ? 'true' : 'false' }}"
        >
            <option value="">— انتخاب کنید —</option>
            @foreach($field->options ?? [] as $opt)
                <option value="{{ $opt }}" @selected($oldVal == $opt)>{{ $opt }}</option>
            @endforeach
        </select>

    @elseif($field->type === 'radio')
        <fieldset class="space-y-2.5 border-0 p-0 m-0" aria-required="{{ $field->is_required ? 'true' : 'false' }}">
            <legend class="sr-only">{{ $field->label }}</legend>
            @foreach($field->options ?? [] as $opt)
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="radio"
                           name="{{ $fieldName }}"
                           value="{{ $opt }}"
                           @checked($oldVal == $opt)
                           @if($field->is_required && $loop->first) required @endif
                           class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 transition">{{ $opt }}</span>
                </label>
            @endforeach
        </fieldset>

    @elseif($field->type === 'checkbox')
        <fieldset class="space-y-2.5 border-0 p-0 m-0">
            <legend class="sr-only">{{ $field->label }}</legend>
            @php $oldCb = old($fieldName, []); if (!is_array($oldCb)) $oldCb = $oldCb ? [$oldCb] : []; @endphp
            @foreach($field->options ?? [] as $opt)
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox"
                           name="{{ $fieldName }}[]"
                           value="{{ $opt }}"
                           @checked(in_array($opt, $oldCb, true))
                           class="w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500">
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 transition">{{ $opt }}</span>
                </label>
            @endforeach
        </fieldset>

    @elseif($field->type === 'rating')
        <div class="star-rating flex items-center gap-1 flex-row-reverse justify-end" role="group" aria-label="{{ $field->label }}">
            @for($star = 5; $star >= 1; $star--)
                <input type="radio" id="{{ $fieldId }}-{{ $star }}" name="{{ $fieldName }}" value="{{ $star }}"
                       @checked((int) $oldVal === $star)
                       @if($field->is_required && $star === 5) required @endif>
                <label for="{{ $fieldId }}-{{ $star }}" aria-label="{{ $star }} ستاره">★</label>
            @endfor
        </div>
    @endif

    @if($field->help_text)
        <p class="text-xs text-slate-400 mt-1.5">{{ $field->help_text }}</p>
    @endif

    @error($fieldName)
        <p class="form-error text-red-500 text-xs mt-1.5 flex items-center gap-1" role="alert">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>
@endif
