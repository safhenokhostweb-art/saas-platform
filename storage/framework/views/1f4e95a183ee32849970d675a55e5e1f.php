
<div x-show="toast.show" x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-2"
     class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] w-full max-w-sm"
     style="display: none;">
    <div :class="toast.type === 'success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'"
         class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border">
        <template x-if="toast.type === 'success'">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
        </template>
        <template x-if="toast.type === 'error'">
            <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
        </template>
        <p class="text-sm font-medium flex-1" x-text="toast.message"></p>
        <button @click="dismissToast()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
    </div>
</div><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/users/_toast.blade.php ENDPATH**/ ?>