{{-- «سلامت ویجت» (WU-3): the Canvas sidebar tab over the Design HUD, for
     both editors. Include it as the tab body, after the editor has called
     TbInspect.attachHost({ …, select: (blockId) => … }) in its init():

       @include('theme-runtime::canvas.health', ['active' => "leftPanelTab === 'health'"])

     `active` is an Alpine expression, true while the tab is open; the
     preview's checks run only then (or while Inspect Mode is on).
     Edit ../../js/canvas-health.js, never a copy. --}}
@once
<style>
.tbh { display: flex; flex-direction: column; gap: .5rem; padding: .75rem; font-size: .75rem; color: #334155; }
.tbh-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; }
.tbh-sum { display: inline-flex; align-items: center; gap: .375rem; min-width: 0; font-weight: 600; }
.tbh-muted { color: #94a3b8; font-weight: 400; }
.tbh-hint { margin: 0; color: #94a3b8; font-size: .6875rem; line-height: 1.6; }
.tbh-dot { flex: none; width: .5rem; height: .5rem; border-radius: 999px; background: #10b981; }
.tbh-dot[data-tone="warn"] { background: #f59e0b; }
.tbh-dot[data-tone="error"] { background: #f43f5e; }
.tbh-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .375rem; }
.tbh-row { border: 1px solid #d1fae5; border-radius: .625rem; background: #f0fdf4; }
.tbh-row[data-tone="warn"] { border-color: #fde68a; background: #fffbeb; }
.tbh-row[data-tone="error"] { border-color: #fecdd3; background: #fff1f2; }
.tbh-line { display: flex; align-items: center; gap: .375rem; padding-inline-end: .5rem; }
.tbh-main { display: flex; align-items: center; gap: .375rem; flex: 1 1 auto; min-width: 0; min-height: 2.25rem; padding: .375rem .5rem; border: 0; background: none; color: inherit; font: inherit; text-align: start; cursor: pointer; border-radius: .625rem; }
.tbh-main:focus-visible, .tbh-btn:focus-visible, .tbh-link:focus-visible { outline: 2px solid #6366f1; outline-offset: 1px; }
.tbh-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; color: #1e293b; }
.tbh-key { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .625rem; color: #94a3b8; }
.tbh-badge { flex: none; font-size: .6875rem; font-weight: 600; color: #047857; }
.tbh-row[data-tone="warn"] .tbh-badge { color: #b45309; }
.tbh-row[data-tone="error"] .tbh-badge { color: #be123c; }
.tbh-actions { display: flex; align-items: center; justify-content: space-between; gap: .375rem; padding: 0 .5rem .5rem; }
.tbh-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 2rem; padding: 0 .625rem; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; color: #475569; font: inherit; font-weight: 600; cursor: pointer; transition: color .15s, border-color .15s, background-color .15s; }
.tbh-btn:hover { color: #4f46e5; border-color: #a5b4fc; }
.tbh-btn--primary { border-color: #6366f1; background: #4f46e5; color: #fff; }
.tbh-btn--primary:hover { background: #4338ca; color: #fff; }
.tbh-btn[data-copied] { border-color: #10b981; background: #059669; color: #fff; }
.tbh-link { min-height: 2rem; padding: 0 .25rem; border: 0; background: none; color: #6366f1; font: inherit; cursor: pointer; }
.tbh-findings { list-style: none; margin: 0; padding: 0 .5rem .5rem; display: flex; flex-direction: column; gap: .375rem; }
.tbh-findings li { padding: .375rem .5rem; border-radius: .5rem; background: rgba(255, 255, 255, .75); line-height: 1.7; }
.tbh-findings code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .625rem; font-weight: 700; color: #be123c; }
.tbh-findings li[data-severity="warning"] code { color: #b45309; }
.tbh-findings li[data-severity="info"] code { color: #64748b; }
.tbh-remedy { margin: .125rem 0 0; color: #64748b; }
.tbh-empty { margin: 0; padding: 1rem .5rem; text-align: center; color: #64748b; line-height: 1.8; }
.tbh-note { margin: 0; color: #be123c; font-size: .6875rem; }
@media (prefers-reduced-motion: reduce) { .tbh-btn { transition: none; } }
</style>
<script>
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::canvasHealthPath()) !!}
</script>
@endonce
<div class="tbh" dir="rtl" data-tb-health x-data="tbHealthPanel()" x-effect="setActive({{ $active ?? 'true' }})">
    <div class="tbh-head">
        <div class="tbh-sum" role="status" aria-live="polite">
            <span class="tbh-dot" :data-tone="summary?.tone || 'ok'" x-show="summary" aria-hidden="true"></span>
            <span x-text="summary ? headline() : 'در حال بررسی ویجت‌ها…'" data-tb-health-headline></span>
        </div>
        <button type="button" class="tbh-btn" x-show="issues().length > 1" x-cloak @click="copyAll()" data-tb-health-copy-all
            :data-copied="copiedKey === '__all__' ? '' : null"
            x-text="copiedKey === '__all__' ? 'کپی شد ✓' : 'کپی همه تسک‌ها'"></button>
    </div>
    <p class="tbh-hint">
        <span x-show="summary" x-text="summary ? `بررسی در عرض ${summary.width}px پیش‌نمایش؛ برای موبایل، دستگاه پیش‌نمایش را موبایل کنید.` : ''"></span>
    </p>
    <p class="tbh-note" x-show="note" x-text="note" role="alert"></p>

    <ul class="tbh-list">
        <template x-for="row in visible()" :key="row.key">
            <li class="tbh-row" :data-tone="row.tone" :data-tb-health-row="row.key">
                <div class="tbh-line">
                    <button type="button" class="tbh-main" @click="select(row)" :title="'انتخاب ' + row.label + ' در بوم'">
                        <span class="tbh-dot" :data-tone="row.tone" aria-hidden="true"></span>
                        <span class="tbh-name" x-text="row.label"></span>
                        <span class="tbh-key" dir="ltr" x-text="row.key"></span>
                    </button>
                    <span class="tbh-badge" x-text="badge(row)"></span>
                </div>
                <template x-if="row.findings.length">
                    <div>
                        <div class="tbh-actions">
                            <button type="button" class="tbh-link" @click="toggle(row)" :aria-expanded="expanded[row.key] ? 'true' : 'false'"
                                x-text="expanded[row.key] ? 'بستن جزئیات' : 'جزئیات'"></button>
                            <button type="button" class="tbh-btn tbh-btn--primary" @click="copy(row)" data-tb-health-copy
                                x-show="row.tone !== 'ok'"
                                :data-copied="copiedKey === row.key ? '' : null"
                                x-text="copiedKey === row.key ? 'کپی شد ✓' : 'کپی پرامپت اصلاحی'"></button>
                        </div>
                        <ul class="tbh-findings" x-show="expanded[row.key]">
                            <template x-for="(f, i) in row.findings" :key="i">
                                <li :data-severity="f.severity">
                                    <code x-text="f.code"></code>
                                    <span x-text="f.message"></span>
                                    <p class="tbh-remedy" x-show="f.remedy" x-text="'راه رفع: ' + f.remedy"></p>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </li>
        </template>
    </ul>

    <p class="tbh-empty" x-show="summary && list.length && !issues().length && !showHealthy">همه ویجت‌های این صفحه سالم‌اند.</p>
    <p class="tbh-empty" x-show="summary && !list.length">این صفحه هنوز ویجتی ندارد.</p>
    <button type="button" class="tbh-btn" x-show="healthy().length" x-cloak @click="showHealthy = !showHealthy" data-tb-health-show-ok
        x-text="showHealthy ? 'پنهان کردن ویجت‌های سالم' : `نمایش ${healthy().length} ویجت سالم`"></button>
</div>
