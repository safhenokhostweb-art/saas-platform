{{-- Row action menu. Expects `row` in the Alpine scope of devopsDashboard. --}}
<div x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape.stop="menu = false" class="relative inline-block text-start">
  <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu"
          :aria-label="'عملیات ' + (row.title || row.domain)"
          class="inline-flex h-11 items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
    عملیات
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </button>
  <div x-show="menu" x-cloak role="menu"
       x-transition:enter="motion-safe:transition motion-safe:ease-out motion-safe:duration-100"
       x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
       class="absolute inset-e-0 z-20 mt-2 w-56 origin-top rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
    <button type="button" role="menuitem" @click="menu = false; openDrawer(row, 'details')"
            class="flex h-11 w-full items-center px-4 text-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
      مشاهده جزئیات مصرف
    </button>
    <template x-if="canPrune">
      <button type="button" role="menuitem" @click="menu = false; openDrawer(row, 'prune')"
              class="flex h-11 w-full items-center px-4 text-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
        پاک‌سازی هوشمند دیسک
      </button>
    </template>
    <button type="button" role="menuitem" @click="menu = false; openDrawer(row, 'quota')"
            class="flex h-11 w-full items-center px-4 text-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
      تغییر سهمیه
    </button>
  </div>
</div>
