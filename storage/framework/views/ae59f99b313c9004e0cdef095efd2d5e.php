"
<div class="bg-white rounded-2xl shadow overflow-hidden">
    
    <div x-show="selectedIds.length > 0" x-transition
         class="p-3 bg-indigo-50 border-b border-indigo-100 flex flex-wrap items-center gap-2"
         style="display: none;">
        <span class="text-sm font-medium text-indigo-700 ml-2">
            <span x-text="selectedIds.length"></span> کاربر انتخاب شده
        </span>
        <button type="button" class="btn btn-sm bg-green-500 text-white hover:bg-green-600" @click="openBulkAction('activate')">
            ✅ فعال‌سازی
        </button>
        <button type="button" class="btn btn-sm bg-gray-500 text-white hover:bg-gray-600" @click="openBulkAction('deactivate')">
            ⛔ غیرفعال‌سازی
        </button>
        <button type="button" class="btn btn-sm bg-red-500 text-white hover:bg-red-600" @click="openBulkAction('delete')">
            🗑️ حذف گروهی
        </button>
        <?php if(request()->get('trashed') == '1'): ?>
            <button type="button" class="btn btn-sm bg-amber-500 text-white hover:bg-amber-600" @click="openBulkAction('restore')">
                ♻️ بازیابی
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary mr-auto" @click="selectedIds = []; selectAll = false">
            ✕ لغو انتخاب
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 w-10">
                        <input type="checkbox" class="form-check-input"
                               :checked="selectAll"
                               @change="toggleSelectAll()">
                    </th>
                    <th class="p-3 font-bold cursor-pointer hover:text-indigo-600 select-none" @click="toggleSort('id')">
                        # <span x-show="sortBy === 'id'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                    </th>
                    <th class="p-3 font-bold">آواتار</th>
                    <th class="p-3 font-bold cursor-pointer hover:text-indigo-600 select-none" @click="toggleSort('mobile')">
                        موبایل <span x-show="sortBy === 'mobile'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                    </th>
                    <th class="p-3 font-bold">ایمیل</th>
                    <th class="p-3 font-bold">نام / پروفایل</th>
                    <th class="p-3 font-bold">نقش‌ها</th>
                    <th class="p-3 font-bold cursor-pointer hover:text-indigo-600 select-none" @click="toggleSort('created_at')">
                        تاریخ ثبت‌نام <span x-show="sortBy === 'created_at'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                    </th>
                    <th class="p-3 font-bold">وضعیت</th>
                    <th class="p-3 font-bold">2FA</th>
                    <th class="p-3 font-bold">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                        :class="{'bg-indigo-50/50': selectedIds.includes('<?php echo e($user->id); ?>')}">
                        <td class="p-3">
                            <input type="checkbox" class="user-checkbox form-check-input"
                                   value="<?php echo e($user->id); ?>"
                                   :checked="selectedIds.includes('<?php echo e($user->id); ?>')"
                                   @change="toggleUser('<?php echo e($user->id); ?>')">
                        </td>
                        <td class="p-3 text-sm text-gray-500">#<?php echo e($user->id); ?></td>
                        <td class="p-3">
                            <?php if($user->profile?->avatar): ?>
                                <img src="<?php echo e($user->profile->avatar); ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm">
                                    <?php echo e(mb_substr($user->mobile, -2)); ?>

                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 font-medium"><?php echo e($user->mobile); ?></td>
                        <td class="p-3 text-sm text-gray-600"><?php echo e($user->email ?? '—'); ?></td>
                        <td class="p-3">
                            <div class="flex flex-col">
                                <span class="font-medium"><?php echo e($user->profile->full_name ?? '—'); ?></span>
                                <?php if($user->profile?->full_name && $user->customer): ?>
                                    <span class="text-xs text-gray-400"><?php echo e($user->customer->company_name ?? $user->customer->name); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <?php $__currentLoopData = $user->roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="inline-flex items-center text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full"><?php echo e($role->name); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php if($user->roles->isEmpty()): ?> <span class="text-gray-400 text-xs">—</span> <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3 text-sm text-gray-600"><?php echo e($user->created_at ? \App\Helpers\Jalali::formatFa($user->created_at, 'Y/m/d H:i') : '—'); ?></td>
                        <td class="p-3">
                            <?php if($user->id !== auth()->id()): ?>
                                <form action="<?php echo e(route('admin.users.toggle', $user)); ?>" method="POST" class="inline">
                                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                    <button type="submit" class="px-2 py-1 rounded text-xs font-medium transition-colors
                                        <?php echo e($user->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'); ?>">
                                        <?php echo e($user->is_active ? 'فعال' : 'غیرفعال'); ?>

                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">خودتان</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-center">
                            <?php if($user->hasTwoFactorEnabled()): ?>
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">✔ فعال</span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 relative" data-row-dropdown>
                            <div class="flex items-center gap-2">
                                <a href="<?php echo e(route('admin.users.edit', $user)); ?>"
                                   class="text-indigo-600 hover:text-indigo-800 text-sm transition-colors" title="ویرایش">✏️</a>

                                <?php if($user->is_admin): ?>
                                    <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-medium">ادمین</span>
                                <?php endif; ?>

                                <?php $hasCustomer = !is_null($user->customer); ?>
                                <?php if($hasCustomer): ?>
                                    <a href="<?php echo e(route('admin.customers.edit', $user->customer)); ?>"
                                       class="text-xs text-blue-600 hover:underline">👤 مشتری</a>
                                <?php endif; ?>

                                <button @click.stop="toggleRowDropdown('<?php echo e($user->id); ?>')"
                                        class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100 transition-colors">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    </svg>
                                </button>

                                <div x-show="openRowId === '<?php echo e($user->id); ?>'"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute left-0 top-full mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1 text-sm"
                                     style="display: none;"
                                     @click.outside="closeRowDropdown()">
                                    <div class="px-3 py-2 text-xs text-gray-400 border-b border-gray-100">
                                        #<?php echo e($user->id); ?> - <?php echo e($user->mobile); ?>

                                    </div>
                                    <a href="<?php echo e(route('admin.users.edit', $user)); ?>"
                                       class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 text-gray-700">
                                        ✏️ ویرایش
                                    </a>
                                    <form action="<?php echo e(route('admin.users.toggle', $user)); ?>" method="POST">
                                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                        <button type="submit"
                                                class="flex items-center gap-2 w-full text-right px-3 py-1.5 hover:bg-gray-50 text-gray-700">
                                            <?php echo e($user->is_active ? '⛔ غیرفعال' : '✅ فعال'); ?>

                                        </button>
                                    </form>
                                    <hr class="my-1 border-gray-100">
                                    <?php if($hasCustomer): ?>
                                        <a href="<?php echo e(route('admin.customers.edit', $user->customer)); ?>"
                                           class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 text-gray-700">
                                            👤 پروفایل مشتری
                                        </a>
                                    <?php endif; ?>
                                    <?php if(auth()->id() !== $user->id): ?>
                                        <button @click.stop="openDeleteModal('<?php echo e($user->id); ?>')"
                                                class="flex items-center gap-2 w-full text-right px-3 py-1.5 text-red-600 hover:bg-red-50">
                                            🗑️ حذف
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <form id="delete-form-<?php echo e($user->id); ?>"
                                      action="<?php echo e(route('admin.users.bulk-delete')); ?>" method="POST" class="hidden">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_ids[]" value="<?php echo e($user->id); ?>">
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="11" class="p-12 text-center">
                            <div class="text-gray-200 text-5xl mb-4">👥</div>
                            <p class="text-gray-500 text-lg mb-2">کاربری یافت نشد.</p>
                            <p class="text-gray-400 text-sm mb-4">سعی کنید فیلترهای جستجو را تغییر دهید.</p>
                            <a href="<?php echo e(route('admin.users.create')); ?>" class="btn btn-primary">+ ایجاد کاربر جدید</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="p-3 border-t border-gray-200 bg-gray-50 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <span class="text-xs text-gray-500">
                نمایش <?php echo e($users->firstItem() ?? 0); ?> - <?php echo e($users->lastItem() ?? 0); ?> از <?php echo e($users->total()); ?> کاربر
            </span>
            <div class="flex items-center gap-1">
                <label class="text-xs text-gray-500">تعداد در صفحه:</label>
                <select @change="changePageSize($event)"
                        class="form-select text-xs py-1 w-20"
                        x-init="$el.value = pageSize">
                    <template x-for="opt in pageSizeOptions" :key="opt">
                        <option :value="opt" x-text="opt"></option>
                    </template>
                </select>
            </div>
        </div>
        <div>
            <?php echo e($users->appends(request()->query())->links()); ?>

        </div>
    </div>
</div>"
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/users/_table.blade.php ENDPATH**/ ?>