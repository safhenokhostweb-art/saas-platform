<?php $__env->startSection('title', 'تیکت ' . $ticket->ticket_number); ?>

<?php $__env->startSection('content'); ?>
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="<?php echo e(route('tickets.admin.index')); ?>" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h1 class="text-xl font-bold text-gray-800"><?php echo e($ticket->subject); ?></h1>
            <span class="font-mono text-xs text-gray-400"><?php echo e($ticket->ticket_number); ?></span>
        </div>
        <div class="flex gap-2">
            <?php echo $__env->make('tickets::components.status-badge', ['status' => $ticket->status], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('tickets::components.priority-badge', ['priority' => $ticket->priority], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        
        <div class="lg:col-span-2 space-y-4">

            
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">
                        <?php echo e(mb_substr($ticket->customer_name ?? 'M', 0, 1)); ?>

                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800"><?php echo e($ticket->customer_name ?? $ticket->customer_email ?? 'مشتری'); ?></p>
                        <p class="text-xs text-gray-400"><?php echo e($ticket->created_at->diffForHumans()); ?></p>
                    </div>
                </div>
                <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap"><?php echo e($ticket->description); ?></p>

                <?php if($ticket->attachments && is_array($ticket->attachments)): ?>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?php $__currentLoopData = $ticket->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(isset($file['path']) && isset($file['name'])): ?>
                        <a href="<?php echo e(asset('storage/' . $file['path'])); ?>" target="_blank"
                           class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1 rounded-full transition">
                            📎 <?php echo e($file['name']); ?>

                        </a>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>
            </div>

            
            <?php $__currentLoopData = $ticket->replies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reply): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('tickets::components.reply-item', ['reply' => $reply], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            
            <?php if($ticket->status !== 'closed'): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">پاسخ</h3>
                <form method="POST" action="<?php echo e(route('tickets.admin.replies.store', $ticket->id)); ?>"
                      enctype="<?php echo e($attachmentsEnabled ? 'multipart/form-data' : 'application/x-www-form-urlencoded'); ?>">
                    <?php echo csrf_field(); ?>
                    <textarea name="message" rows="4" required
                              placeholder="متن پاسخ..."
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-300"></textarea>
                    <?php if($attachmentsEnabled): ?>
                    <input type="file" name="attachments[]" multiple class="mb-3 text-sm">
                    <?php endif; ?>
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                        ارسال پاسخ
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        
        <div class="space-y-4">

            
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-sm">
                <h3 class="font-semibold text-gray-700 mb-3">اطلاعات تیکت</h3>
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt class="text-gray-500">سایت</dt><dd class="font-medium"><?php echo e($ticket->site?->name ?? '—'); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">مسئول</dt><dd class="font-medium"><?php echo e($ticket->assignee?->name ?? '—'); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">دسته</dt><dd class="font-medium"><?php echo e($ticket->category ?? '—'); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">ایجاد</dt><dd class="text-xs"><?php echo e($ticket->created_at->format('Y/m/d')); ?></dd></div>
                    <?php if($slaEnabled && !empty($slaMetrics)): ?>
                    <div class="flex justify-between"><dt class="text-gray-500">نرخ نقض SLA</dt><dd class="text-red-500"><?php echo e($slaMetrics['breach_rate'] ?? 0); ?>%</dd></div>
                    <?php endif; ?>
                </dl>
            </div>

            
            <?php if($ticket->status !== 'closed'): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-3 text-sm">تغییر وضعیت</h3>
                <div class="flex flex-col gap-2">
                    <?php if($ticket->status === 'open'): ?>
                    <form method="POST" action="<?php echo e(route('tickets.admin.status.update', $ticket->id)); ?>">
                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                        <input type="hidden" name="status" value="in_progress">
                        <button class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm py-2 rounded-lg transition">
                            شروع بررسی
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo e(route('tickets.admin.status.update', $ticket->id)); ?>">
                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                        <input type="hidden" name="status" value="closed">
                        <button class="w-full bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm py-2 rounded-lg transition">
                            بستن تیکت
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <form method="POST" action="<?php echo e(route('tickets.admin.status.update', $ticket->id)); ?>">
                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <input type="hidden" name="status" value="open">
                    <button class="w-full bg-yellow-50 hover:bg-yellow-100 text-yellow-700 text-sm py-2 rounded-lg transition">
                        بازگشایی تیکت
                    </button>
                </form>
            </div>
            <?php endif; ?>

            
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-3 text-sm">تخصیص</h3>
                <form method="POST" action="<?php echo e(route('tickets.admin.assign', $ticket->id)); ?>">
                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <select name="assigned_to" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 focus:outline-none">
                        <option value="">بدون مسئول</option>
                        
                    </select>
                    <button class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-2 rounded-lg transition">
                        تخصیص
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('tickets.layouts.admin', 'admin.layout'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\tickets\src\Providers/../../Resources/views/admin/tickets/show.blade.php ENDPATH**/ ?>