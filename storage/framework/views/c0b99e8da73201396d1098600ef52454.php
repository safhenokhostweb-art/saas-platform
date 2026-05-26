<?php $__env->startSection('title', 'مدیریت تیکت‌ها'); ?>

<?php $__env->startSection('content'); ?>
<div class="p-6">

    
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">تیکت‌ها</h1>
        <a href="<?php echo e(route('tickets.admin.create')); ?>"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            تیکت جدید
        </a>
    </div>

    
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <?php $__currentLoopData = [['total','کل','gray'],['open','باز','yellow'],['in_progress','در حال بررسی','blue'],['closed','بسته','green']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key, $label, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-500 mb-1"><?php echo e($label); ?></p>
            <p class="text-2xl font-bold text-gray-800"><?php echo e($stats[$key] ?? 0); ?></p>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <form method="GET" class="flex flex-wrap gap-3 mb-6 bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <input type="text" name="q" value="<?php echo e($filters['q'] ?? ''); ?>"
               placeholder="جستجو..."
               class="flex-1 min-w-48 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">

        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="">همه وضعیت‌ها</option>
            <option value="open" <?php if(($filters['status'] ?? '') === 'open'): echo 'selected'; endif; ?>>باز</option>
            <option value="in_progress" <?php if(($filters['status'] ?? '') === 'in_progress'): echo 'selected'; endif; ?>>در حال بررسی</option>
            <option value="closed" <?php if(($filters['status'] ?? '') === 'closed'): echo 'selected'; endif; ?>>بسته</option>
        </select>

        <select name="priority" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <option value="">همه اولویت‌ها</option>
            <option value="high" <?php if(($filters['priority'] ?? '') === 'high'): echo 'selected'; endif; ?>>فوری</option>
            <option value="normal" <?php if(($filters['priority'] ?? '') === 'normal'): echo 'selected'; endif; ?>>معمولی</option>
            <option value="low" <?php if(($filters['priority'] ?? '') === 'low'): echo 'selected'; endif; ?>>کم</option>
        </select>

        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-2 rounded-lg transition">
            فیلتر
        </button>
    </form>

    
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <?php if($tickets->isEmpty()): ?>
            <div class="text-center py-16 text-gray-400">
                <p class="text-lg">هیچ تیکتی یافت نشد.</p>
                <a href="<?php echo e(route('tickets.admin.create')); ?>" class="text-blue-600 text-sm mt-2 inline-block">ایجاد تیکت جدید</a>
            </div>
        <?php else: ?>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-right">شماره</th>
                    <th class="px-4 py-3 text-right">موضوع</th>
                    <th class="px-4 py-3 text-right">وضعیت</th>
                    <th class="px-4 py-3 text-right">اولویت</th>
                    <th class="px-4 py-3 text-right">سایت</th>
                    <?php if($slaEnabled): ?>
                    <th class="px-4 py-3 text-right">SLA</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-right">تاریخ</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo e($ticket->ticket_number); ?></td>
                    <td class="px-4 py-3 font-medium text-gray-800 max-w-xs truncate"><?php echo e($ticket->subject); ?></td>
                    <td class="px-4 py-3">
                        <?php echo $__env->make('tickets::components.status-badge', ['status' => $ticket->status], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php echo $__env->make('tickets::components.priority-badge', ['priority' => $ticket->priority], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?php echo e($ticket->site?->name ?? '—'); ?></td>
                    <?php if($slaEnabled): ?>
                    <td class="px-4 py-3">
                        
                        <span class="text-xs text-gray-400">—</span>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-gray-400 text-xs"><?php echo e($ticket->created_at->diffForHumans()); ?></td>
                    <td class="px-4 py-3">
                        <a href="<?php echo e(route('tickets.admin.show', $ticket->id)); ?>"
                           class="text-blue-600 hover:underline text-xs">مشاهده</a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">
            <?php echo e($tickets->withQueryString()->links()); ?>

        </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('tickets.layouts.admin', 'admin.layout'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\tickets\src\Providers/../../Resources/views/admin/tickets/index.blade.php ENDPATH**/ ?>