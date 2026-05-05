{{-- جعبهٔ آموزش ماژول: عنوان + تاریخ آخرین بروزرسانی. با هر تغییر در آن ماژول، محتوا و تاریخ را به‌روز کنید. --}}
<details class="mt-4 border border-gray-200 rounded-xl bg-blue-50/50 overflow-hidden" id="module-guide-{{ isset($id) ? $id : 'default' }}">
    <summary class="px-4 py-3 cursor-pointer font-semibold text-gray-800 hover:bg-blue-100/50 flex items-center gap-2 list-none">
        <span class="text-indigo-600">?</span>
        {{ $guideTitle }} — آخرین بروزرسانی: {{ $updated }}
    </summary>
    <div class="px-4 py-3 pt-0 text-sm text-gray-700 space-y-4 border-t border-gray-200">
        {{ $slot }}
    </div>
</details>
