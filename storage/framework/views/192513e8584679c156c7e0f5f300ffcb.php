<?php $__env->startSection('header', 'مدیریت کاربران پلتفرم'); ?>

<?php $__env->startSection('content'); ?>

<?php echo $__env->make('admin.users.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" action="<?php echo e(route('admin.users.index')); ?>" class="flex flex-wrap items-center gap-2">
        <input type="text" name="mobile" class="form-control w-48" placeholder="جستجو با موبایل" value="<?php echo e(request('mobile')); ?>">
        <?php if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')): ?>
        <select name="status" class="form-select w-40">
            <option value="">همه وضعیت‌ها</option>
            <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>فعال</option>
            <option value="inactive" <?php echo e(request('status') === 'inactive' ? 'selected' : ''); ?>>غیرفعال</option>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">جستجو</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 font-bold">#</th>
                    <th class="p-3 font-bold">موبایل</th>
                    <th class="p-3 font-bold">نام (پروفایل)</th>
                    <th class="p-3 font-bold">تاریخ ثبت‌نام</th>
                    <?php if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')): ?>
                    <th class="p-3 font-bold">وضعیت</th>
                    <?php endif; ?>
                    <th class="p-3 font-bold">مشتری مرتبط</th>
                    <th class="p-3 font-bold">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="p-3"><?php echo e($user->id); ?></td>
                        <td class="p-3 font-medium"><?php echo e($user->mobile); ?></td>
                        <td class="p-3"><?php echo e($user->profile->full_name ?? '—'); ?></td>
                        <td class="p-3 text-sm text-gray-600"><?php echo e($user->created_at ? \App\Helpers\Jalali::formatFa($user->created_at, 'Y/m/d H:i') : '—'); ?></td>
                        <?php if(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')): ?>
                        <td class="p-3">
                            <?php if($user->id !== auth()->id()): ?>
                                <form action="<?php echo e(route('admin.users.toggle', $user)); ?>" method="POST" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <button type="submit" class="px-2 py-1 rounded text-sm <?php echo e($user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'); ?>">
                                        <?php echo e($user->is_active ? 'فعال' : 'غیرفعال'); ?>

                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-500">شما</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="p-3">
                            <?php if($user->customer): ?>
                                <a href="<?php echo e(route('admin.customers.edit', $user->customer)); ?>" class="text-blue-600 hover:underline"><?php echo e($user->customer->company_name ?: $user->customer->name); ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="p-3">
                            <?php if($user->is_admin): ?>
                                <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded">ادمین</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="<?php echo e(\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active') ? 7 : 6); ?>" class="p-6 text-center text-gray-500">کاربری یافت نشد.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-gray-200">
        <?php echo e($users->links()); ?>

    </div>
</div>

<?php if(!\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')): ?>
<div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
    برای فعال/غیرفعال کردن اکانت کاربران، در ترمینال اجرا کنید: <code class="bg-amber-100 px-1 rounded">php artisan migrate</code>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/users/index.blade.php ENDPATH**/ ?>