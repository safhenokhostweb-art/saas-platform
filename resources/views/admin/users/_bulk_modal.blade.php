{{-- Bulk Action Confirmation Modal (Alpine.js) --}}
<div x-show="bulkActionModalOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/40 backdrop-blur-sm"
     style="display: none;"
     @keydown.escape.window="cancelBulkAction()"
     @click.self="cancelBulkAction()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="text-center">
            <template x-if="bulkActionType === 'delete'">
                <div class="mx-auto w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
            </template>
            <template x-if="bulkActionType !== 'delete'">
                <div class="mx-auto w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </template>

            <h3 class="text-lg font-bold text-gray-900 mb-2">
                <span x-text="getBulkActionLabel(bulkActionType)"></span> گروهی
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                آیا از <span class="font-bold" x-text="selectedIds.length"></span> کاربر
                <span x-text="getBulkActionLabel(bulkActionType)?.toLowerCase()"></span> اطمینان دارید؟
            </p>

            <div class="flex gap-3 justify-center">
                <button @click="confirmBulkAction()"
                        :class="bulkActionType === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                        class="btn text-white px-6">
                    <span x-text="bulkActionType === 'delete' ? 'بله، حذف کن' : 'تأیید'"></span>
                </button>
                <button @click="cancelBulkAction()"
                        class="btn btn-outline-secondary px-6">
                    انصراف
                </button>
            </div>
        </div>
    </div>
</div>