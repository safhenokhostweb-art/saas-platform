<?php $__env->startSection('title', 'داشبورد پشتیبانی'); ?>

<?php $__env->startPush('head'); ?>
<style>
/* ── Base ─────────────────────────────────────── */
:root{--c-indigo:99,102,241;--c-purple:168,85,247;--c-amber:245,158,11;--c-red:239,68,68;--c-green:16,185,129;--c-blue:59,130,246;--c-teal:20,184,166}
/* ── Stat Cards ────────────────────────────────── */
.tkt-stat{background:linear-gradient(135deg,rgba(255,255,255,.92),rgba(255,255,255,.72));backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.35);border-radius:1.25rem;padding:1.25rem 1.5rem;transition:all .3s ease;box-shadow:0 4px 20px rgba(15,23,42,.06);position:relative;overflow:hidden}
.tkt-stat::before{content:'';position:absolute;inset:0;border-radius:inherit;opacity:0;transition:opacity .3s ease;pointer-events:none}
.tkt-stat:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(99,102,241,.12)}
.tkt-stat:hover::before{opacity:1}
.tkt-stat__accent{position:absolute;top:0;right:0;width:3px;height:100%;border-radius:0 1.25rem 1.25rem 0}
.tkt-stat__icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0;transition:transform .3s ease}
.tkt-stat:hover .tkt-stat__icon{transform:scale(1.1) rotate(-5deg)}
.tkt-stat__val{font-size:1.875rem;font-weight:800;line-height:1;letter-spacing:-.03em}
.tkt-stat__label{font-size:.725rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;opacity:.6;margin-top:.35rem}
.tkt-stat__trend{display:inline-flex;align-items:center;gap:.25rem;font-size:.7rem;font-weight:600;padding:.2rem .6rem;border-radius:9999px;margin-top:.5rem}
/* ── SLA Ring ──────────────────────────────────── */
.sla-ring-wrap{position:relative;width:56px;height:56px;flex-shrink:0}
.sla-ring-wrap svg{transform:rotate(-90deg)}
.sla-ring-val{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.625rem;font-weight:800;color:#374151}
/* ── Priority Heatmap ──────────────────────────── */
.heatmap-cell{transition:all .2s ease;cursor:pointer;border-radius:.5rem}
.heatmap-cell:hover{transform:scale(1.05);z-index:10}
/* ── Agent Cards ───────────────────────────────── */
.agent-card{background:rgba(255,255,255,.8);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.3);border-radius:1rem;padding:1rem;transition:all .25s ease;box-shadow:0 2px 12px rgba(15,23,42,.04)}
.agent-card:hover{box-shadow:0 8px 28px rgba(99,102,241,.1);transform:translateY(-2px)}
.agent-workload{height:6px;border-radius:9999px;background:#e5e7eb;overflow:hidden;margin-top:.5rem}
.agent-workload-bar{height:100%;border-radius:9999px;transition:width .6s cubic-bezier(.34,1.56,.64,1)}
/* ── Queue Health ──────────────────────────────── */
.queue-health-bar{height:10px;border-radius:9999px;background:#f3f4f6;overflow:hidden;position:relative}
.queue-health-fill{height:100%;border-radius:9999px;transition:width .8s cubic-bezier(.34,1.56,.64,1)}
/* ── Glass Table ───────────────────────────────── */
.glass-card-strong{background:rgba(255,255,255,.84);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.3);border-radius:1.5rem;box-shadow:0 8px 32px rgba(15,23,42,.06)}
.glass-filter-bar{background:rgba(255,255,255,.78);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.25);border-radius:1rem;padding:1rem 1.5rem;box-shadow:0 8px 24px rgba(15,23,42,.04)}
.ticket-row{transition:all .2s ease;position:relative}
.ticket-row:hover{background:rgba(99,102,241,.03)}
.ticket-row::after{content:'';position:absolute;right:0;top:50%;transform:translateY(-50%);width:0;height:60%;background:rgba(99,102,241,.15);border-radius:0 4px 4px 0;transition:width .2s ease}
.ticket-row:hover::after{width:3px}
/* ── Status Badges ─────────────────────────────── */
.tkt-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .85rem;border-radius:9999px;font-size:.72rem;font-weight:700;white-space:nowrap;border:1px solid transparent;letter-spacing:.01em}
/* ── Priority Dot ──────────────────────────────── */
.pdot{display:inline-block!important;width:7px!important;height:7px!important;border-radius:50%!important;animation:pdot-pulse 2s ease-in-out infinite!important}
@keyframes pdot-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(1.6)}}
/* ── Skeleton ──────────────────────────────────── */
.skeleton{background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);background-size:200% 100%;animation:skeleton-wave 1.4s ease infinite;border-radius:.5rem}
@keyframes skeleton-wave{0%{background-position:200% 0}100%{background-position:-200% 0}}
/* ── Escalation Alert ──────────────────────────── */
.escalation-alert{background:linear-gradient(135deg,rgba(239,68,68,.08),rgba(245,158,11,.06));border:1px solid rgba(239,68,68,.2);border-radius:1rem;animation:alert-pulse 3s ease-in-out infinite}
@keyframes alert-pulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}50%{box-shadow:0 0 0 6px rgba(239,68,68,.08)}}
/* ── Buttons ───────────────────────────────────── */
.btn-gradient{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1.5rem;border-radius:.85rem;font-weight:700;font-size:.875rem;transition:all .25s ease;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,.25);text-decoration:none;letter-spacing:.01em}
.btn-gradient:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(99,102,241,.38)}
/* ── Section Divider ───────────────────────────── */
.section-divider{display:flex;align-items:center;gap:.75rem;margin:2rem 0 1rem}
.section-divider span{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;white-space:nowrap}
.section-divider hr{flex:1;border:none;border-top:1px solid #e2e8f0}
/* ── Response Time ─────────────────────────────── */
.resp-bar{height:20px;background:#f8fafc;border-radius:.5rem;overflow:hidden;position:relative}
.resp-fill{height:100%;border-radius:.5rem;display:flex;align-items:center;justify-content:flex-end;padding-right:.5rem;font-size:.65rem;font-weight:700;color:#fff;min-width:2rem;transition:width .8s cubic-bezier(.34,1.56,.64,1)}
/* ── Feature Pills ─────────────────────────────── */
.feature-pill{background:rgba(255,255,255,.75);border:1px solid rgba(99,102,241,.12);border-radius:.85rem;padding:.75rem 1rem;transition:all .25s ease;cursor:default}
.feature-pill:hover{border-color:rgba(99,102,241,.3);box-shadow:0 4px 16px rgba(99,102,241,.1);transform:translateY(-2px)}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="admin-page" style="gap:0" x-data="{
    search: '',
    status: '',
    priority: '',
    bulkSelected: [],
    get allIds() { return Array.from(document.querySelectorAll('.ticket-check')).map(el=>el.value); },
    toggleAll(checked) { this.bulkSelected = checked ? this.allIds : []; },
    get bulkCount() { return this.bulkSelected.length; }
}">

    
    <?php if(session('success')): ?>
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5"
         x-data="{ show: true }" x-show="show" x-transition.duration.300ms>
        <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm text-green-800 font-medium flex-1"><?php echo e(session('success')); ?></span>
        <button @click="show=false" class="text-green-400 hover:text-green-600 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <?php endif; ?>

    
    <?php $openUrgent = $tickets->where('status','open')->where('priority','high')->count() ?? 0; ?>
    <?php if($openUrgent > 0): ?>
    <div class="escalation-alert flex items-center gap-4 px-5 py-3.5 mb-5">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 shrink-0">
            <svg class="w-5 h-5 text-red-600 animate-bounce" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-red-700"><?php echo e($openUrgent); ?> تیکت اورژانسی باز — نیاز به توجه فوری</p>
            <p class="text-xs text-red-600 mt-0.5">این تیکت‌ها اولویت بالا دارند و هنوز پاسخ نگرفته‌اند.</p>
        </div>
        <a href="?status=open&priority=high" class="text-xs font-semibold text-red-600 hover:text-red-700 underline underline-offset-2 shrink-0">مشاهده همه</a>
    </div>
    <?php endif; ?>

    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-<?php echo e($slaEnabled ? '6' : '5'); ?> gap-4 mb-6">

        
        <div class="tkt-stat col-span-2 sm:col-span-1">
            <div class="tkt-stat__accent bg-gradient-to-b from-indigo-500 to-purple-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-gradient-to-br from-indigo-50 to-purple-50 text-indigo-600">📫</div>
                <div>
                    <div class="tkt-stat__val text-gray-900"><?php echo e(\App\Helpers\NumberHelper::formatFa($stats['total'] ?? 0)); ?></div>
                    <div class="tkt-stat__label text-gray-500">کل تیکت‌ها</div>
                </div>
            </div>
        </div>

        
        <div class="tkt-stat" x-data="{}" @click="document.querySelector('[x-model=\'status\']').dispatchEvent(new Event('change')); search=''; status='open'">
            <div class="tkt-stat__accent bg-gradient-to-b from-yellow-400 to-orange-400"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-yellow-50 text-yellow-600">
                    <span class="relative flex h-6 w-6 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-30"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
                    </span>
                </div>
                <div>
                    <div class="tkt-stat__val text-yellow-700"><?php echo e(\App\Helpers\NumberHelper::formatFa($stats['open'] ?? 0)); ?></div>
                    <div class="tkt-stat__label text-yellow-600">باز</div>
                    <?php if(($stats['open'] ?? 0) > 0): ?>
                    <span class="tkt-stat__trend bg-yellow-100 text-yellow-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        نیاز به پاسخ
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <div class="tkt-stat cursor-pointer" @click="status='in_progress'">
            <div class="tkt-stat__accent bg-gradient-to-b from-blue-400 to-indigo-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-blue-50 text-blue-600">
                    <svg class="w-6 h-6 animate-spin" style="animation-duration:3s" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-blue-700"><?php echo e(\App\Helpers\NumberHelper::formatFa($stats['in_progress'] ?? 0)); ?></div>
                    <div class="tkt-stat__label text-blue-600">در حال بررسی</div>
                </div>
            </div>
        </div>

        
        <div class="tkt-stat cursor-pointer" @click="status='closed'">
            <div class="tkt-stat__accent bg-gradient-to-b from-green-400 to-teal-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-green-50 text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-green-700"><?php echo e(\App\Helpers\NumberHelper::formatFa($stats['closed'] ?? 0)); ?></div>
                    <div class="tkt-stat__label text-green-600">بسته شده</div>
                </div>
            </div>
        </div>

        
        <div class="tkt-stat">
            <div class="tkt-stat__accent bg-gradient-to-b from-teal-400 to-cyan-500"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon bg-teal-50 text-teal-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val text-teal-700 text-2xl"><?php echo e(\App\Helpers\NumberHelper::formatFa($stats['avg_response_hours'] ?? 4)); ?>h</div>
                    <div class="tkt-stat__label text-teal-600">میانگین پاسخ</div>
                    <span class="tkt-stat__trend bg-teal-50 text-teal-700">SLA سالم</span>
                </div>
            </div>
        </div>

        
        <?php if($slaEnabled): ?>
        <?php $breachCount = $stats['sla_breaches'] ?? 0; ?>
        <div class="tkt-stat <?php echo e($breachCount > 0 ? 'ring-2 ring-red-300' : ''); ?>">
            <div class="tkt-stat__accent <?php echo e($breachCount > 0 ? 'bg-gradient-to-b from-red-400 to-red-600' : 'bg-gradient-to-b from-gray-300 to-gray-400'); ?>"></div>
            <div class="flex items-start gap-3">
                <div class="tkt-stat__icon <?php echo e($breachCount > 0 ? 'bg-red-50 text-red-600' : 'bg-gray-50 text-gray-500'); ?>">
                    <svg class="w-6 h-6 <?php echo e($breachCount > 0 ? 'animate-pulse' : ''); ?>" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <div>
                    <div class="tkt-stat__val <?php echo e($breachCount > 0 ? 'text-red-700' : 'text-gray-600'); ?>"><?php echo e(\App\Helpers\NumberHelper::formatFa($breachCount)); ?></div>
                    <div class="tkt-stat__label <?php echo e($breachCount > 0 ? 'text-red-600' : 'text-gray-500'); ?>">نقض SLA</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                سلامت صف
            </h3>
            <?php
            $total = max(1, $stats['total'] ?? 1);
            $openPct = round(($stats['open'] ?? 0) / $total * 100);
            $inPct   = round(($stats['in_progress'] ?? 0) / $total * 100);
            $closPct = round(($stats['closed'] ?? 0) / $total * 100);
            $queueScore = $openPct < 30 ? 'سالم' : ($openPct < 60 ? 'متوسط' : 'بحرانی');
            $queueColor = $openPct < 30 ? 'text-green-600' : ($openPct < 60 ? 'text-amber-600' : 'text-red-600');
            ?>
            <div class="space-y-3 text-sm">
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">باز</span>
                        <span class="font-bold text-yellow-600 text-xs"><?php echo e($openPct); ?>%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-yellow-400 to-orange-400" style="width:<?php echo e($openPct); ?>%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">در حال بررسی</span>
                        <span class="font-bold text-blue-600 text-xs"><?php echo e($inPct); ?>%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-blue-400 to-indigo-500" style="width:<?php echo e($inPct); ?>%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-1.5">
                        <span class="text-gray-500 text-xs">بسته</span>
                        <span class="font-bold text-green-600 text-xs"><?php echo e($closPct); ?>%</span>
                    </div>
                    <div class="queue-health-bar">
                        <div class="queue-health-fill bg-gradient-to-r from-green-400 to-teal-500" style="width:<?php echo e($closPct); ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-500">وضعیت کلی صف</span>
                <span class="text-xs font-bold <?php echo e($queueColor); ?>"><?php echo e($queueScore); ?></span>
            </div>
        </div>

        
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                نقشه حرارتی اولویت
            </h3>
            <?php
            $highOpen   = $tickets->where('priority','high')->where('status','open')->count();
            $highInProg = $tickets->where('priority','high')->where('status','in_progress')->count();
            $normOpen   = $tickets->where('priority','normal')->where('status','open')->count();
            $normInProg = $tickets->where('priority','normal')->where('status','in_progress')->count();
            $lowOpen    = $tickets->where('priority','low')->where('status','open')->count();
            $lowInProg  = $tickets->where('priority','low')->where('status','in_progress')->count();
            $heatMax    = max(1, $highOpen, $highInProg, $normOpen, $normInProg, $lowOpen, $lowInProg);
            function heatColor($v, $max, $base) {
                $pct = $v / $max;
                if($pct === 0) return 'bg-gray-100 text-gray-400';
                if($pct < .33) return "bg-{$base}-100 text-{$base}-700";
                if($pct < .66) return "bg-{$base}-200 text-{$base}-800";
                return "bg-{$base}-400 text-white";
            }
            ?>
            <div class="overflow-x-auto">
            <table class="w-full text-xs text-center">
                <thead>
                    <tr class="text-gray-400 font-semibold">
                        <th class="py-1 text-right pr-1">اولویت</th>
                        <th class="py-1 px-2">باز</th>
                        <th class="py-1 px-2">در جریان</th>
                    </tr>
                </thead>
                <tbody class="space-y-2">
                    <tr>
                        <td class="text-right text-red-600 font-bold py-1.5 pr-1">بالا</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold <?php echo e(heatColor($highOpen, $heatMax, 'red')); ?>"><?php echo e($highOpen); ?></div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold <?php echo e(heatColor($highInProg, $heatMax, 'red')); ?>"><?php echo e($highInProg); ?></div></td>
                    </tr>
                    <tr>
                        <td class="text-right text-blue-600 font-bold py-1.5 pr-1">عادی</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold <?php echo e(heatColor($normOpen, $heatMax, 'blue')); ?>"><?php echo e($normOpen); ?></div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold <?php echo e(heatColor($normInProg, $heatMax, 'blue')); ?>"><?php echo e($normInProg); ?></div></td>
                    </tr>
                    <tr>
                        <td class="text-right text-gray-500 font-bold py-1.5 pr-1">کم</td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold <?php echo e(heatColor($lowOpen, $heatMax, 'gray')); ?>"><?php echo e($lowOpen); ?></div></td>
                        <td class="py-1.5 px-2"><div class="heatmap-cell h-10 flex items-center justify-center font-bold <?php echo e(heatColor($lowInProg, $heatMax, 'gray')); ?>"><?php echo e($lowInProg); ?></div></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        
        <div class="glass-card-strong p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                بار کاری کارشناسان
            </h3>
            <?php
            $agentWorkloads = $tickets->whereNotNull('assigned_to')->groupBy('assigned_to');
            $maxAgentTickets = max(1, $agentWorkloads->map->count()->max() ?? 1);
            ?>
            <?php if($agentWorkloads->isEmpty()): ?>
            <div class="text-center py-4 text-gray-400">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                <p class="text-xs">تیکتی تخصیص نیافته</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php $__currentLoopData = $agentWorkloads->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agentId => $agentTickets): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                $agentName = $agentTickets->first()?->assignee?->name ?? ('کارشناس ' . $agentId);
                $agentCount = $agentTickets->count();
                $agentPct = round($agentCount / $maxAgentTickets * 100);
                $barColor = $agentPct > 75 ? 'bg-red-500' : ($agentPct > 50 ? 'bg-amber-400' : 'bg-indigo-500');
                ?>
                <div class="agent-card">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                <?php echo e(mb_substr($agentName, 0, 1)); ?>

                            </div>
                            <span class="text-xs font-semibold text-gray-700 truncate max-w-[100px]"><?php echo e($agentName); ?></span>
                        </div>
                        <span class="text-xs font-bold <?php echo e($agentPct > 75 ? 'text-red-600' : 'text-gray-600'); ?>"><?php echo e($agentCount); ?></span>
                    </div>
                    <div class="agent-workload mt-2">
                        <div class="<?php echo e($barColor); ?> agent-workload-bar" style="width:<?php echo e($agentPct); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="<?php echo e(route('tickets.admin.create')); ?>" class="btn-gradient">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                تیکت جدید
            </a>
            
            <div x-show="bulkCount > 0" x-transition class="flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-xl px-3 py-2">
                <span class="text-xs font-semibold text-indigo-700" x-text="`${bulkCount} انتخاب شده`"></span>
                <button class="text-xs text-indigo-600 hover:text-indigo-800 font-medium border-r border-indigo-200 pr-2 mr-1">بستن گروهی</button>
                <button @click="bulkSelected=[]" class="text-xs text-gray-500 hover:text-gray-700">انصراف</button>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if($slaEnabled): ?>
            <a href="<?php echo e(route('tickets.admin.sla.index')); ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition-all">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                داشبورد SLA
            </a>
            <?php endif; ?>
            <a href="<?php echo e(route('tickets.admin.settings')); ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-white hover:bg-gray-50 border border-gray-200 transition-all shadow-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات
            </a>
        </div>
    </div>

    

    
    <div class="glass-filter-bar mb-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="relative flex-1 w-full sm:w-auto">
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" placeholder="جستجوی موضوع یا شماره تیکت..."
                       class="w-full pr-9 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all">
            </div>
            <select x-model="status" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه وضعیت‌ها</option>
                <option value="open">باز</option>
                <option value="in_progress">در حال بررسی</option>
                <option value="closed">بسته</option>
            </select>
            <select x-model="priority" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition-all w-full sm:w-auto">
                <option value="">همه اولویت‌ها</option>
                <option value="high">🔴 بالا</option>
                <option value="normal">🔵 عادی</option>
                <option value="low">⚪ کم</option>
            </select>
            <button @click="search=''; status=''; priority=''"
                    class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-white/80 text-sm text-gray-500 hover:bg-gray-50 transition-all w-full sm:w-auto justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                پاک کردن
            </button>
        </div>
    </div>

    
    <div class="glass-card-strong overflow-hidden">
    <div class="overflow-x-auto scrollbar-thin">
    <table class="w-full text-right border-collapse" style="min-width:700px">
    <thead>
        <tr class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
            <th class="p-3 pr-5 w-8">
                <input type="checkbox" class="rounded text-indigo-600"
                       @change="toggleAll($event.target.checked)">
            </th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">شماره</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">سایت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">موضوع</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">وضعیت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">اولویت</th>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">مسئول</th>
            <?php if($slaEnabled): ?><th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">SLA</th><?php endif; ?>
            <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">تاریخ</th>
            <th class="p-3 pl-5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">عملیات</th>
        </tr>
    </thead>
    <tbody>
    <?php $__empty_1 = true; $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
        $p     = $ticket->priority ?? 'normal';
        $pBg   = $p === 'high' ? 'bg-red-50 text-red-700 border-red-200' : ($p === 'low' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-600 border-blue-200');
        $pDot  = $p === 'high' ? 'bg-red-500' : ($p === 'low' ? 'bg-gray-400' : 'bg-blue-500');
        $pLbl  = $p === 'high' ? 'بالا' : ($p === 'low' ? 'کم' : 'عادی');
    ?>
    <tr class="ticket-row border-b border-gray-50"
        data-subject="<?php echo e($ticket->subject); ?>"
        data-ticket-no="<?php echo e($ticket->ticket_number); ?>"
        data-status="<?php echo e($ticket->status); ?>"
        data-priority="<?php echo e($p); ?>"
        x-show="
            (!search || $el.dataset.subject.toLowerCase().includes(search.toLowerCase()) || $el.dataset.ticketNo.includes(search)) &&
            (!status || $el.dataset.status === status) &&
            (!priority || $el.dataset.priority === priority)
        "
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0">

        
        <td class="p-3 pr-5">
            <input type="checkbox" class="ticket-check rounded text-indigo-600"
                   value="<?php echo e($ticket->id); ?>"
                   x-model="bulkSelected">
        </td>

        
        <td class="p-3">
            <span class="font-mono font-bold text-indigo-700 text-xs bg-indigo-50 px-2 py-1 rounded-lg border border-indigo-100"><?php echo e($ticket->ticket_number); ?></span>
        </td>

        
        <td class="p-3">
            <?php if($ticket->site): ?>
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 shrink-0"></span>
                <span class="text-xs text-gray-700 font-medium"><?php echo e($ticket->site->name); ?></span>
            </div>
            <?php else: ?>
            <span class="text-xs text-gray-400">—</span>
            <?php endif; ?>
        </td>

        
        <td class="p-3">
            <a href="<?php echo e(route('tickets.admin.show', $ticket->id)); ?>" class="group">
                <span class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 transition-colors"><?php echo e(Str::limit($ticket->subject, 45)); ?></span>
                <?php if($ticket->category): ?>
                <span class="ml-2 text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded"><?php echo e($ticket->category); ?></span>
                <?php endif; ?>
            </a>
        </td>

        
        <td class="p-3">
            <?php if($ticket->status === 'open'): ?>
                <span class="tkt-badge bg-yellow-50 text-yellow-700 border-yellow-200">
                    <span class="pdot bg-yellow-500"></span>باز
                </span>
            <?php elseif($ticket->status === 'in_progress'): ?>
                <span class="tkt-badge bg-blue-50 text-blue-700 border-blue-200">
                    <span class="pdot bg-blue-500"></span>در حال بررسی
                </span>
            <?php else: ?>
                <span class="tkt-badge bg-green-50 text-green-700 border-green-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>بسته
                </span>
            <?php endif; ?>
        </td>

        
        <td class="p-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo e($pBg); ?>">
                <span class="pdot <?php echo e($pDot); ?>"></span><?php echo e($pLbl); ?>

            </span>
        </td>

        
        <td class="p-3">
            <?php if($ticket->assignee): ?>
            <div class="flex items-center gap-1.5">
                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                    <?php echo e(mb_substr($ticket->assignee->name, 0, 1)); ?>

                </div>
                <span class="text-xs text-gray-700 truncate max-w-[80px]"><?php echo e($ticket->assignee->name); ?></span>
            </div>
            <?php else: ?>
            <span class="text-xs text-gray-300 italic">تخصیص نیافته</span>
            <?php endif; ?>
        </td>

        
        <?php if($slaEnabled): ?>
        <td class="p-3">
            <span class="text-xs text-gray-400">—</span>
        </td>
        <?php endif; ?>

        
        <td class="p-3 text-xs text-gray-500 whitespace-nowrap">
            <?php echo e(\App\Helpers\Jalali::formatFa($ticket->created_at, 'Y/m/d')); ?>

            <div class="text-[10px] text-gray-400"><?php echo e(\App\Helpers\Jalali::formatFa($ticket->created_at, 'H:i')); ?></div>
        </td>

        
        <td class="p-3 pl-5">
            <a href="<?php echo e(route('tickets.admin.show', $ticket->id)); ?>"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-all hover:shadow-sm whitespace-nowrap">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                مشاهده
            </a>
        </td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <tr>
        <td colspan="<?php echo e($slaEnabled ? 10 : 9); ?>" class="text-center py-16">
            <div class="flex flex-col items-center gap-3">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center text-3xl shadow-sm">📭</div>
                <p class="text-gray-600 font-semibold">هیچ تیکتی یافت نشد</p>
                <p class="text-gray-400 text-sm">فیلترها را تغییر دهید یا تیکت جدید ثبت کنید.</p>
                <a href="<?php echo e(route('tickets.admin.create')); ?>" class="btn-gradient !text-sm !py-2 !px-5 mt-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    ثبت تیکت جدید
                </a>
            </div>
        </td>
    </tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
    </div>

    
    <?php if(method_exists($tickets, 'links')): ?>
    <div class="mt-6 flex justify-center">
        <?php echo e($tickets->withQueryString()->links()); ?>

    </div>
    <?php endif; ?>

    
    <div class="section-divider">
        <hr><span>قابلیت‌های ماژول پشتیبانی</span><hr>
    </div>
    <?php
    $features = [
        ['icon' => '💬', 'label' => 'چت آنی', 'desc' => 'رابط چت با حباب‌های رنگی و اسکرول خودکار', 'color' => 'indigo'],
        ['icon' => '📱', 'label' => 'اعلان پیامکی', 'desc' => 'SMS خودکار برای رویدادهای تیکت', 'color' => 'purple'],
        ['icon' => '⚡', 'label' => 'SLA هوشمند', 'desc' => 'ردیابی و هشدار نقض توافق سطح خدمت', 'color' => 'amber'],
        ['icon' => '👤', 'label' => 'تخصیص کارشناس', 'desc' => 'مدیریت کامل تخصیص و سابقه تغییرات', 'color' => 'blue'],
        ['icon' => '🔗', 'label' => 'چند سایته', 'desc' => 'تخصیص تیکت به سایت دلخواه', 'color' => 'teal'],
        ['icon' => '📝', 'label' => 'یادداشت داخلی', 'desc' => 'یادداشت‌های محرمانه برای کارشناسان', 'color' => 'green'],
        ['icon' => '🤖', 'label' => 'API پروکسی', 'desc' => 'اتصال سایت مشتری از طریق API', 'color' => 'sky'],
        ['icon' => '🔔', 'label' => 'ناظران تیکت', 'desc' => 'سیستم CC و ناظران خودکار', 'color' => 'rose'],
        ['icon' => '📊', 'label' => 'داشبورد تحلیلی', 'desc' => 'آمار، نقشه حرارتی و بار کاری', 'color' => 'violet'],
        ['icon' => '♾️', 'label' => 'حاکمیت یکپارچه', 'desc' => 'یک منبع معتبر برای تمام پروژه‌ها', 'color' => 'slate'],
    ];
    ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mt-4">
        <?php $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="feature-pill">
            <div class="text-2xl mb-2"><?php echo e($feat['icon']); ?></div>
            <p class="text-xs font-bold text-gray-800"><?php echo e($feat['label']); ?></p>
            <p class="text-[10px] text-gray-400 mt-0.5 leading-relaxed"><?php echo e($feat['desc']); ?></p>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(config('tickets.layouts.admin', 'admin.layout'), \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\tickets\src\Providers/../../Resources/views/admin/tickets/index.blade.php ENDPATH**/ ?>