{{-- Single Delete Confirmation Modal (Alpine.js) --}}
<div x-show="deleteModalOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/40 backdrop-blur-sm"
     style="display: none;"
     @keydown.escape.window="cancelDelete()"
     @click.self="cancelDelete()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">حذف کاربر</h3>
            <p class="text-sm text-gray-600 mb-6">آیا از حذف این کاربر اطمینان دارید؟</p>
            <div class="flex gap-3 justify-center">
                <button @click="confirmDelete()" class="btn bg-red-600 text-white hover:bg-red-700 px-6">
                    بله، حذف کن
                </button>
                <button @click="cancelDelete()" class="btn btn-outline-secondary px-6">
                    انصراف
                </button>
            </div>
        </div>
    </div>
</div>