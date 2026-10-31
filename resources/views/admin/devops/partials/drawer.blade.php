{{-- Side drawer for details / prune / quota. Lives inside devopsDashboard's scope. --}}
{{-- Above the layout's bell (z 60) and floating help widgets. --}}
<div x-show="drawer.open" x-cloak class="fixed inset-0 z-1000">
  <div class="absolute inset-0 bg-gray-900/40 motion-safe:transition-opacity" @click="closeDrawer()"
       x-show="drawer.open" x-transition.opacity></div>

  <aside role="dialog" aria-modal="true" aria-labelledby="devops-drawer-title"
         class="absolute inset-y-0 inset-e-0 flex w-full max-w-lg flex-col bg-white shadow-xl"
         x-show="drawer.open"
         x-transition:enter="motion-safe:transition motion-safe:ease-out motion-safe:duration-200"
         x-transition:enter-start="opacity-0 -translate-x-6" x-transition:enter-end="opacity-100 translate-x-0">

    <header class="flex items-start justify-between gap-4 border-b border-gray-200 p-5">
      <div class="min-w-0">
        <h2 id="devops-drawer-title" x-ref="drawerTitle" tabindex="-1" class="text-lg font-bold text-gray-900 focus:outline-none"
            x-text="{ details: 'جزئیات مصرف', prune: 'پاک‌سازی هوشمند دیسک', quota: 'تغییر سهمیه' }[drawer.mode]"></h2>
        <p class="mt-0.5 truncate text-sm text-gray-600">
          <span x-text="drawer.tenant?.title"></span> · <span dir="ltr" x-text="drawer.tenant?.domain"></span>
        </p>
      </div>
      <button type="button" @click="closeDrawer()" :disabled="drawer.busy" aria-label="بستن"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </header>

    <div class="flex-1 space-y-5 overflow-y-auto p-5">
      <div x-show="drawer.message" role="status" aria-live="polite"
           class="rounded-lg px-4 py-3 text-sm"
           :class="drawer.messageOk ? 'border border-emerald-200 bg-emerald-50 text-emerald-800' : 'border border-rose-200 bg-rose-50 text-rose-800'"
           x-text="drawer.message"></div>

      {{-- Usage summary (all modes) --}}
      <template x-if="drawer.tenant">
        <div class="rounded-xl border border-gray-200 p-4">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">مصرف دیسک</span>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="badgeFor(drawer.tenant.danger_level)">
              <span class="h-1.5 w-1.5 rounded-full" :class="dotFor(drawer.tenant.danger_level)"></span>
              <span x-text="drawer.tenant.danger_label"></span>
            </span>
          </div>
          <p class="mt-2 text-xl font-bold text-gray-900" x-show="drawer.tenant.disk_usage_bytes !== null">
            <span x-text="drawer.tenant.disk_usage_human"></span>
            <span class="text-sm font-normal text-gray-600">از <span x-text="drawer.tenant.soft_limit_human"></span> (<span x-text="drawer.tenant.percent_human"></span>)</span>
          </p>
          <p class="mt-2 text-sm text-gray-700" x-show="drawer.tenant.disk_usage_bytes === null">
            هنوز اندازه‌گیری نشده · سهمیهٔ نرم <span x-text="drawer.tenant.soft_limit_human"></span>
          </p>
          <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full" :class="barFor(drawer.tenant.danger_level)" :style="'width:' + Math.min(100, drawer.tenant.percent ?? 0) + '%'"></div>
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
            <div><dt class="text-gray-600">سهمیهٔ سخت</dt><dd class="mt-0.5 font-medium text-gray-900" x-text="drawer.tenant.hard_limit_human || 'تعریف نشده'"></dd></div>
            <div><dt class="text-gray-600">آخرین بررسی</dt><dd class="mt-0.5 font-medium text-gray-900" x-text="drawer.tenant.audited_human"></dd></div>
            <div><dt class="text-gray-600">سقف پردازنده</dt><dd class="mt-0.5 font-medium text-gray-900" x-text="drawer.tenant.cpu_cores_limit !== null ? drawer.tenant.cpu_cores_human + ' هسته' : 'تعریف نشده'"></dd></div>
            <div><dt class="text-gray-600">سقف حافظه</dt><dd class="mt-0.5 font-medium text-gray-900" x-text="drawer.tenant.memory_limit_bytes !== null ? drawer.tenant.memory_limit_human : 'تعریف نشده'"></dd></div>
          </dl>
          <p x-show="drawer.tenant.audit_error" class="mt-3 text-xs text-rose-700" x-text="'آخرین خطای اندازه‌گیری: ' + drawer.tenant.audit_error"></p>
          <button type="button" @click="runAudit()" :disabled="drawer.busy"
                  class="mt-4 inline-flex h-11 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            اندازه‌گیری مجدد اکنون
          </button>
        </div>
      </template>

      {{-- Details: prune history --}}
      <template x-if="drawer.mode === 'details'">
        <div>
          <h3 class="text-sm font-bold text-gray-900">سوابق پاک‌سازی</h3>
          <p x-show="drawer.history.length === 0" class="mt-2 text-sm text-gray-600">هنوز پاک‌سازی یا پیش‌نمایشی ثبت نشده است.</p>
          <ul x-show="drawer.history.length > 0" class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200">
            <template x-for="h in drawer.history" :key="h.id">
              <li class="flex items-center justify-between gap-3 p-3 text-xs">
                <div>
                  <p class="font-medium text-gray-900"><span x-text="modeLabel(h.mode)"></span> · <span x-text="profileLabel(h.profile)"></span> · <span x-text="triggerLabel(h.triggered_by)"></span></p>
                  <p class="mt-0.5 text-gray-600" x-text="h.created_human"></p>
                </div>
                <div class="text-end">
                  <p class="font-bold text-gray-900" x-text="h.reclaimed_human"></p>
                  <p :class="h.status === 'completed' ? 'text-emerald-700' : (h.status === 'failed' ? 'text-rose-700' : 'text-amber-700')" x-text="statusLabel(h.status)"></p>
                </div>
              </li>
            </template>
          </ul>
        </div>
      </template>

      {{-- Prune: preview first, then one-click cleanup --}}
      <template x-if="drawer.mode === 'prune'">
        <div class="space-y-4">
          <div class="rounded-lg bg-indigo-50 p-3 text-xs leading-6 text-indigo-900">
            فقط فایل‌های بازتولیدپذیر حذف می‌شوند: لاگ‌های قدیمی، کش و ویوهای کامپایل‌شده، نشست‌های منقضی، فایل‌های موقت آپلود و نسخه‌های قدیمی پایگاه داده (همیشه جدیدترین نسخه‌ها نگه داشته می‌شوند).
            رسانه‌ها، پوشهٔ public و storage/app/public هرگز لمس نمی‌شوند. هر اجرا در سوابق ممیزی ثبت می‌شود.
          </div>

          <fieldset>
            <legend class="text-sm font-medium text-gray-900">پروفایل نگهداری</legend>
            <div class="mt-2 grid grid-cols-2 gap-2">
              <template x-for="p in profiles" :key="p">
                <label class="flex h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm"
                       :class="drawer.profile === p ? 'border-indigo-500 bg-indigo-50 text-indigo-900' : 'border-gray-300 text-gray-700'">
                  <input type="radio" name="devops-profile" :value="p" x-model="drawer.profile" @change="drawer.preview = null; drawer.token = null"
                         class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                  <span x-text="profileLabel(p)"></span>
                </label>
              </template>
            </div>
          </fieldset>

          <button type="button" @click="runPreview()" :disabled="drawer.busy"
                  class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-white px-4 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            <span x-show="drawer.busy && !drawer.token" class="h-4 w-4 rounded-full border-2 border-indigo-600 border-e-transparent motion-safe:animate-spin" aria-hidden="true"></span>
            <span x-text="drawer.preview ? 'پیش‌نمایش دوباره' : 'پیش‌نمایش حجم قابل آزادسازی'"></span>
          </button>

          <template x-if="drawer.preview">
            <div class="rounded-xl border border-gray-200">
              <div class="flex items-center justify-between border-b border-gray-200 p-4">
                <span class="text-sm text-gray-600">قابل آزادسازی</span>
                <span class="text-lg font-bold text-gray-900"><span x-text="drawer.preview.reclaimed_human"></span>
                  <span class="text-xs font-normal text-gray-600">در <span x-text="drawer.preview.pruned_items_human"></span> فایل</span></span>
              </div>
              <ul class="divide-y divide-gray-100 text-sm">
                <template x-for="c in drawer.preview.categories" :key="c.key">
                  <li class="p-3" x-data="{ more: false }">
                    <div class="flex items-center justify-between">
                      <span class="text-gray-800" x-text="c.label"></span>
                      <span class="text-xs text-gray-600"><span x-text="c.count_human"></span> فایل · <span class="font-medium text-gray-900" x-text="c.human"></span></span>
                    </div>
                    <button type="button" x-show="c.sample.length" @click="more = !more" :aria-expanded="more"
                            class="mt-1 text-xs text-indigo-700 hover:underline" x-text="more ? 'پنهان کردن نمونه‌ها' : 'نمایش نمونه فایل‌ها'"></button>
                    <ul x-show="more" class="mt-1 space-y-0.5 text-start text-[11px] text-gray-600" dir="ltr">
                      <template x-for="s in c.sample" :key="s"><li class="truncate" x-text="s"></li></template>
                    </ul>
                  </li>
                </template>
              </ul>
              <p x-show="drawer.preview.capped" class="border-t border-gray-200 p-3 text-xs text-amber-800">تعداد فایل‌ها از سقف هر اجرا بیشتر است؛ باقی‌مانده در اجرای بعدی پاک می‌شود.</p>
            </div>
          </template>

          <button type="button" @click="runPrune()" :disabled="drawer.busy || !drawer.token || !drawer.preview || drawer.preview.pruned_items_count === 0"
                  class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 text-sm font-medium text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
            <span x-show="drawer.busy && drawer.token" class="h-4 w-4 rounded-full border-2 border-white border-e-transparent motion-safe:animate-spin" aria-hidden="true"></span>
            پاک‌سازی فوری
          </button>
          <p class="text-center text-xs text-gray-600" x-show="!drawer.preview">برای فعال شدن پاک‌سازی، ابتدا پیش‌نمایش بگیرید.</p>
          <p class="text-center text-xs text-gray-600" x-show="drawer.preview && drawer.preview.pruned_items_count === 0">فایلی برای پاک‌سازی با این پروفایل وجود ندارد.</p>

          <template x-if="drawer.result">
            <div class="rounded-xl border p-4 text-sm"
                 :class="drawer.result.status === 'completed' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
              <p class="font-bold text-gray-900">آزاد شد: <span x-text="drawer.result.reclaimed_human"></span> در <span x-text="drawer.result.pruned_items_human"></span> فایل</p>
              <p class="mt-1 text-xs text-gray-700">گزارش ممیزی #<span x-text="drawer.result.id"></span> · <span x-text="statusLabel(drawer.result.status)"></span></p>
              <p x-show="drawer.result.error" class="mt-1 text-xs text-rose-700" x-text="drawer.result.error"></p>
            </div>
          </template>
        </div>
      </template>

      {{-- Quota editor --}}
      <template x-if="drawer.mode === 'quota'">
        <form class="space-y-4" @submit.prevent="saveQuota()" novalidate>
          <p class="text-xs leading-6 text-gray-600">
            سهمیه‌ها به‌صورت تأمین نازک (thin-provisioned) تخصیص داده می‌شوند: مجموع سهمیه‌ها می‌تواند از ظرفیت فیزیکی بیشتر باشد.
            وضعیت هشدار و بحرانی نسبت به سهمیهٔ نرم محاسبه می‌شود.
          </p>
          <div>
            <label for="q-soft" class="block text-sm font-medium text-gray-900">سهمیهٔ نرم دیسک (گیگابایت)</label>
            <input id="q-soft" type="number" step="0.1" min="0.1" max="10000" required inputmode="decimal" x-model="drawer.quota.disk_soft_limit_gb"
                   class="mt-1 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
          </div>
          <div>
            <label for="q-hard" class="block text-sm font-medium text-gray-900">سهمیهٔ سخت دیسک (گیگابایت، اختیاری)</label>
            <input id="q-hard" type="number" step="0.1" min="0.1" max="10000" inputmode="decimal" x-model="drawer.quota.disk_hard_limit_gb"
                   class="mt-1 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            <p class="mt-1 text-xs text-gray-600">باید بزرگ‌تر یا مساوی سهمیهٔ نرم باشد. خالی = بدون سقف سخت.</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="q-cpu" class="block text-sm font-medium text-gray-900">سقف هسته CPU</label>
              <input id="q-cpu" type="number" step="0.1" min="0.1" max="256" inputmode="decimal" x-model="drawer.quota.cpu_cores_limit"
                     class="mt-1 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
              <label for="q-mem" class="block text-sm font-medium text-gray-900">سقف حافظه (مگابایت)</label>
              <input id="q-mem" type="number" step="1" min="64" max="1048576" inputmode="numeric" x-model="drawer.quota.memory_limit_mb"
                     class="mt-1 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
          </div>
          <p class="text-xs text-gray-600">سقف پردازنده و حافظه برای برنامه‌ریزی ظرفیت ثبت می‌شود و در حال حاضر روی سرور اعمال نمی‌شود.</p>
          <button type="submit" :disabled="drawer.busy"
                  class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
            ذخیرهٔ سهمیه
          </button>
        </form>
      </template>
    </div>
  </aside>
</div>
