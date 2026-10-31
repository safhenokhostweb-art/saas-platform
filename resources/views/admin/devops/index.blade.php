@extends('layouts.admin')

@section('title', 'پایش و پاک‌سازی دیسک سایت‌ها')

@section('content')
<div x-data="devopsDashboard(@js($payload), @js(['canPrune' => $canPrune, 'profiles' => $profiles, 'search' => $search]))"
     x-init="init()"
     @keydown.escape.window="closeDrawer()"
     class="space-y-6"
     dir="rtl">

  {{-- ─── Header ─────────────────────────────────────────────────────────── --}}
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">پایش و پاک‌سازی دیسک سایت‌ها</h1>
      <p class="mt-1 text-sm text-gray-600">
        مصرف واقعی دیسک هر سایت در برابر سهمیهٔ نرم، وضعیت سرور و پاک‌سازی ایمن و ممیزی‌شدهٔ فایل‌های بازتولیدپذیر.
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span class="inline-flex h-9 items-center gap-2 rounded-full px-3 text-xs font-medium"
            :class="data.auto_prune_enabled ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-gray-100 text-gray-700 ring-1 ring-gray-200'">
        <span class="h-2 w-2 rounded-full" :class="data.auto_prune_enabled ? 'bg-emerald-500' : 'bg-gray-400'"></span>
        <span x-text="data.auto_prune_enabled ? 'پاک‌سازی خودکار: فعال' : 'پاک‌سازی خودکار: فقط پیش‌نمایش'"></span>
      </span>
      <a href="{{ route('admin.resource-governance.index') }}"
         class="inline-flex h-11 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
        حاکمیت منابع
      </a>
      <button type="button" @click="refresh(true)" :disabled="loading"
              class="inline-flex h-11 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
        <svg class="h-4 w-4" :class="loading && 'motion-safe:animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        به‌روزرسانی
      </button>
    </div>
  </div>

  <div x-show="error" x-cloak role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" x-text="error"></div>

  {{-- ─── Metric tiles ───────────────────────────────────────────────────── --}}
  <section aria-label="شاخص‌های سرور" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    {{-- Panel host disk --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium text-gray-600">دیسک سرور پنل</p>
          <p class="mt-1 text-2xl font-bold text-gray-900" x-text="data.server.disk.used_human"></p>
          <p class="mt-1 text-xs text-gray-600">
            از <span x-text="data.server.disk.total_human"></span> ·
            آزاد <span x-text="data.server.disk.free_human"></span>
          </p>
        </div>
        <div class="relative h-16 w-16 shrink-0" role="img" :aria-label="'مصرف دیسک ' + data.server.disk.percent_human">
          <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90" aria-hidden="true">
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" class="stroke-gray-100"/>
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" stroke-linecap="round"
                    :class="strokeFor(levelFor(data.server.disk.used_percent))"
                    :stroke-dasharray="dash(data.server.disk.used_percent)"
                    class="motion-safe:transition-all motion-safe:duration-700"/>
          </svg>
          <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-800" x-text="data.server.disk.percent_human"></span>
        </div>
      </div>
    </div>

    {{-- Memory --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium text-gray-600">حافظهٔ سرور (RAM)</p>
          <p class="mt-1 text-2xl font-bold text-gray-900" x-text="data.server.memory.used_human"></p>
          <p class="mt-1 text-xs text-gray-600" x-show="data.server.memory.total_bytes !== null">از <span x-text="data.server.memory.total_human"></span></p>
          <p class="mt-1 text-xs text-gray-600" x-show="data.server.memory.total_bytes === null">اطلاعات حافظه روی این سرور در دسترس نیست.</p>
        </div>
        <div class="relative h-16 w-16 shrink-0" role="img" :aria-label="'مصرف حافظه ' + data.server.memory.percent_human">
          <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90" aria-hidden="true">
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" class="stroke-gray-100"/>
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" stroke-linecap="round"
                    :class="strokeFor(levelFor(data.server.memory.used_percent))"
                    :stroke-dasharray="dash(data.server.memory.used_percent)"
                    class="motion-safe:transition-all motion-safe:duration-700"/>
          </svg>
          <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-800" x-text="data.server.memory.percent_human"></span>
        </div>
      </div>
    </div>

    {{-- CPU --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-medium text-gray-600">فشار پردازنده (CPU)</p>
          <p class="mt-1 text-2xl font-bold text-gray-900" x-text="data.server.cpu.percent_human"></p>
          <p class="mt-1 text-xs text-gray-600" x-show="data.server.cpu.load_1m !== null">
            میانگین بار ۱/۵/۱۵ دقیقه: <span x-text="data.server.cpu.load_human"></span> ·
            <span x-text="data.server.cpu.cores_human"></span> هسته
          </p>
          <p class="mt-1 text-xs text-gray-600" x-show="data.server.cpu.load_1m === null">بار پردازنده روی این سرور قابل خواندن نیست.</p>
        </div>
        <div class="relative h-16 w-16 shrink-0" role="img" :aria-label="'فشار پردازنده ' + data.server.cpu.percent_human">
          <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90" aria-hidden="true">
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" class="stroke-gray-100"/>
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" stroke-linecap="round"
                    :class="strokeFor(levelFor(data.server.cpu.percent))"
                    :stroke-dasharray="dash(data.server.cpu.percent)"
                    class="motion-safe:transition-all motion-safe:duration-700"/>
          </svg>
        </div>
      </div>
    </div>

    {{-- Allocated vs consumed (thin provisioning) --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <p class="text-xs font-medium text-gray-600">سهمیهٔ تخصیص‌یافته به سایت‌ها</p>
      <p class="mt-1 text-2xl font-bold text-gray-900" x-text="data.pool.allocated_human"></p>
      <p class="mt-1 text-xs text-gray-600">مصرف واقعی: <span class="font-medium text-gray-800" x-text="data.pool.consumed_human"></span> · <span x-text="data.pool.sites_human"></span> سایت</p>
    </div>

    {{-- Physical pool --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <p class="text-xs font-medium text-gray-600">فضای فیزیکی سرورهای میزبان</p>
      <p class="mt-1 text-2xl font-bold text-gray-900" x-text="data.pool.physical_free_human"></p>
      <p class="mt-1 text-xs text-gray-600" x-show="data.pool.physical_bytes !== null">
        آزاد از <span x-text="data.pool.physical_human"></span> ·
        <span x-text="data.pool.servers_human"></span> سرور اندازه‌گیری‌شده
      </p>
      <p class="mt-1 text-xs text-gray-600" x-show="data.pool.physical_bytes === null">پس از اولین اندازه‌گیری خودکار سرورها نمایش داده می‌شود.</p>
    </div>

    {{-- Over-allocation ratio --}}
    <div class="rounded-xl border bg-white p-5"
         :class="(data.pool.overcommit_ratio ?? 0) > 1 ? 'border-amber-300' : 'border-gray-200'">
      <p class="text-xs font-medium text-gray-600">نسبت تخصیص بیش از ظرفیت</p>
      <p class="mt-1 text-2xl font-bold"
         :class="(data.pool.overcommit_ratio ?? 0) > 1 ? 'text-amber-700' : 'text-gray-900'"
         x-text="data.pool.overcommit_human"></p>
      <p class="mt-1 text-xs text-gray-600"
         x-text="data.pool.overcommit_ratio === null ? 'پس از اولین اندازه‌گیری سرورها محاسبه می‌شود.' : ((data.pool.overcommit_ratio > 1) ? 'بیش از ظرفیت فیزیکی سهمیه داده شده است.' : 'سهمیه‌ها در محدودهٔ ظرفیت فیزیکی است.')"></p>
    </div>
  </section>

  {{-- ─── Danger-zone summary ────────────────────────────────────────────── --}}
  <section aria-label="وضعیت سایت‌ها" class="flex flex-wrap items-center gap-2 text-sm">
    <template x-for="lvl in ['normal','warning','critical','unknown']" :key="lvl">
      <button type="button" @click="levelFilter = (levelFilter === lvl ? null : lvl)"
              :aria-pressed="levelFilter === lvl"
              class="inline-flex h-11 items-center gap-2 rounded-full px-4 font-medium ring-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
              :class="badgeFor(lvl) + (levelFilter === lvl ? ' ring-2' : '')">
        <span class="h-2 w-2 rounded-full" :class="dotFor(lvl)"></span>
        <span x-text="labelFor(lvl)"></span>
        <span class="font-bold" x-text="data.pool.levels_human[lvl]"></span>
      </button>
    </template>
    <span class="text-xs text-gray-600 ms-auto">
      عادی زیر {{ num_fa($payload['thresholds']['warning_percent']) }}٪ ·
      هشدار {{ num_fa($payload['thresholds']['warning_percent']) }} تا {{ num_fa($payload['thresholds']['critical_percent']) }}٪ ·
      بحرانی از {{ num_fa($payload['thresholds']['critical_percent']) }}٪ سهمیهٔ نرم
    </span>
  </section>

  {{-- ─── Tenant matrix ──────────────────────────────────────────────────── --}}
  <section class="rounded-xl border border-gray-200 bg-white" aria-labelledby="tenant-matrix-title">
    <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between">
      <h2 id="tenant-matrix-title" class="text-base font-bold text-gray-900">ماتریس مصرف سایت‌ها</h2>
      <label class="relative block w-full sm:w-80">
        <span class="sr-only">جست‌وجوی سایت</span>
        <svg class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
        <input type="search" x-model="search" @input.debounce.400ms="refresh(false)" maxlength="100"
               placeholder="جست‌وجو بر اساس نام یا دامنه…"
               class="h-11 w-full rounded-lg border border-gray-300 bg-white ps-9 pe-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
      </label>
    </div>

    {{-- Desktop / tablet table --}}
    <div class="hidden md:block overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-600">
          <tr>
            <th scope="col" class="px-4 py-3 text-start font-medium">سایت</th>
            <th scope="col" class="px-4 py-3 text-start font-medium">مصرف / سهمیهٔ نرم</th>
            <th scope="col" class="px-4 py-3 text-start font-medium">وضعیت</th>
            <th scope="col" class="px-4 py-3 text-start font-medium">آخرین بررسی</th>
            <th scope="col" class="px-4 py-3 text-end font-medium"><span class="sr-only">عملیات</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <template x-for="row in visibleTenants" :key="row.site_id">
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <p class="font-medium text-gray-900" x-text="row.title || '—'"></p>
                <p class="text-xs text-gray-600" dir="ltr" x-text="row.domain"></p>
              </td>
              <td class="px-4 py-3 w-80">
                <div class="flex items-center justify-between text-xs text-gray-700">
                  <span><span class="font-medium" x-text="row.disk_usage_human"></span> از <span x-text="row.soft_limit_human"></span></span>
                  <span class="font-bold" x-text="row.percent_human"></span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar"
                     aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.min(100, row.percent ?? 0)" :aria-valuetext="row.percent_human">
                  <div class="h-full rounded-full motion-safe:transition-all motion-safe:duration-500" :class="barFor(row.danger_level)" :style="'width:' + Math.min(100, row.percent ?? 0) + '%'"></div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="badgeFor(row.danger_level)">
                  <span class="h-1.5 w-1.5 rounded-full" :class="dotFor(row.danger_level)"></span>
                  <span x-text="row.danger_label"></span>
                </span>
                <p x-show="row.audit_error" class="mt-1 max-w-48 truncate text-xs text-rose-700" :title="row.audit_error" x-text="'خطا: ' + row.audit_error"></p>
              </td>
              <td class="px-4 py-3 text-xs text-gray-600" x-text="row.audited_human"></td>
              <td class="px-4 py-3 text-end">
                @include('admin.devops.partials.row-actions')
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    {{-- Mobile cards --}}
    <ul class="divide-y divide-gray-100 md:hidden">
      <template x-for="row in visibleTenants" :key="'m' + row.site_id">
        <li class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-medium text-gray-900" x-text="row.title || '—'"></p>
              <p class="truncate text-xs text-gray-600" dir="ltr" x-text="row.domain"></p>
            </div>
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="badgeFor(row.danger_level)">
              <span class="h-1.5 w-1.5 rounded-full" :class="dotFor(row.danger_level)"></span>
              <span x-text="row.danger_label"></span>
            </span>
          </div>
          <div class="mt-3 flex items-center justify-between text-xs text-gray-700">
            <span><span class="font-medium" x-text="row.disk_usage_human"></span> از <span x-text="row.soft_limit_human"></span></span>
            <span class="font-bold" x-text="row.percent_human"></span>
          </div>
          <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar"
               aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.min(100, row.percent ?? 0)" :aria-valuetext="row.percent_human">
            <div class="h-full rounded-full" :class="barFor(row.danger_level)" :style="'width:' + Math.min(100, row.percent ?? 0) + '%'"></div>
          </div>
          <div class="mt-3 flex items-center justify-between">
            <span class="text-xs text-gray-600" x-text="row.audited_human"></span>
            @include('admin.devops.partials.row-actions')
          </div>
        </li>
      </template>
    </ul>

    <div x-show="visibleTenants.length === 0" class="p-10 text-center text-sm text-gray-600">
      <span x-text="search || levelFilter ? 'سایتی با این فیلتر یافت نشد.' : 'هنوز سایتی ثبت نشده است.'"></span>
    </div>
  </section>

  {{-- ─── Drawer ─────────────────────────────────────────────────────────── --}}
  @include('admin.devops.partials.drawer')
</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function devopsDashboard(initial, opts) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const routes = {
    metrics: @js(route('admin.devops.resources.metrics')),
    base:    @js(url('admin/devops/resources')),
  };

  return {
    data: initial,
    canPrune: opts.canPrune,
    profiles: opts.profiles,
    search: opts.search || '',
    levelFilter: null,
    loading: false,
    error: null,
    timer: null,

    drawer: { open: false, mode: null, tenant: null, history: [], busy: false, message: null, messageOk: true,
              profile: 'routine', preview: null, token: null, result: null, quota: {} },

    init() {
      this.timer = setInterval(() => { if (!document.hidden && !this.drawer.busy) this.refresh(false, true); }, 30000);
    },

    get visibleTenants() {
      return this.levelFilter ? this.data.tenants.filter(r => r.danger_level === this.levelFilter) : this.data.tenants;
    },

    async request(url, options = {}) {
      const res = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest',
                   ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers || {}) },
      });
      let body = {};
      try { body = await res.json(); } catch (e) { body = {}; }
      if (!res.ok) {
        const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
        const err = new Error(firstError || body.message || 'درخواست ناموفق بود. دوباره تلاش کنید.');
        err.body = body;
        throw err;
      }
      return body;
    },

    async refresh(fresh, silent = false) {
      if (!silent) this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.search.trim()) params.set('q', this.search.trim());
        if (fresh) params.set('fresh', '1');
        this.data = await this.request(routes.metrics + (params.toString() ? '?' + params : ''));
        this.error = null;
      } catch (e) {
        if (!silent) this.error = e.message;
      } finally {
        this.loading = false;
      }
    },

    replaceTenant(tenant) {
      if (!tenant) return;
      const i = this.data.tenants.findIndex(r => r.site_id === tenant.site_id);
      if (i >= 0) this.data.tenants.splice(i, 1, tenant);
      this.drawer.tenant = tenant;
    },

    async openDrawer(row, mode) {
      this.drawer = { ...this.drawer, open: true, mode, tenant: row, history: [], busy: false, message: null,
                      messageOk: true, profile: row.danger_level === 'critical' ? 'aggressive' : 'routine',
                      preview: null, token: null, result: null,
                      quota: {
                        disk_soft_limit_gb: +(row.soft_limit_bytes / 1073741824).toFixed(2),
                        disk_hard_limit_gb: row.hard_limit_bytes > 0 ? +(row.hard_limit_bytes / 1073741824).toFixed(2) : '',
                        cpu_cores_limit: row.cpu_cores_limit ?? '',
                        memory_limit_mb: row.memory_limit_bytes ? Math.round(row.memory_limit_bytes / 1048576) : '',
                      } };
      this.$nextTick(() => this.$refs.drawerTitle?.focus());
      if (mode === 'details') {
        try {
          const body = await this.request(routes.base + '/' + row.site_id);
          this.drawer.history = body.history;
          this.replaceTenant(body.tenant);
        } catch (e) { this.flash(e.message, false); }
      }
    },

    closeDrawer() {
      if (this.drawer.busy) return;
      this.drawer.open = false;
    },

    flash(message, ok = true) { this.drawer.message = message; this.drawer.messageOk = ok; },

    async runPreview() {
      this.drawer.busy = true; this.drawer.preview = null; this.drawer.token = null; this.drawer.result = null; this.drawer.message = null;
      try {
        const body = await this.request(routes.base + '/' + this.drawer.tenant.site_id + '/preview', {
          method: 'POST', body: JSON.stringify({ profile: this.drawer.profile }) });
        this.drawer.preview = body.audit;
        this.drawer.token = body.confirmation_token;
      } catch (e) { this.flash(e.message, false); }
      finally { this.drawer.busy = false; }
    },

    async runPrune() {
      if (!this.drawer.token) return;
      this.drawer.busy = true; this.drawer.message = null;
      try {
        const body = await this.request(routes.base + '/' + this.drawer.tenant.site_id + '/prune', {
          method: 'POST', body: JSON.stringify({ confirmation_token: this.drawer.token }) });
        this.drawer.result = body.audit;
        this.flash(body.message, body.audit.status === 'completed');
        await this.refresh(false, true);
        this.replaceTenant(this.data.tenants.find(r => r.site_id === this.drawer.tenant.site_id));
      } catch (e) {
        this.flash(e.message, false);
        if (e.body?.audit) this.drawer.result = e.body.audit;
      } finally {
        this.drawer.token = null; // single use: a new preview is needed for another run
        this.drawer.busy = false;
      }
    },

    async runAudit() {
      this.drawer.busy = true; this.drawer.message = null;
      try {
        const body = await this.request(routes.base + '/' + this.drawer.tenant.site_id + '/audit', { method: 'POST' });
        this.replaceTenant(body.tenant);
        this.flash('اندازه‌گیری انجام شد.');
      } catch (e) { this.flash(e.message, false); }
      finally { this.drawer.busy = false; }
    },

    async saveQuota() {
      this.drawer.busy = true; this.drawer.message = null;
      const q = this.drawer.quota;
      const payload = { disk_soft_limit_gb: q.disk_soft_limit_gb };
      for (const k of ['disk_hard_limit_gb', 'cpu_cores_limit', 'memory_limit_mb']) payload[k] = q[k] === '' ? null : q[k];
      try {
        const body = await this.request(routes.base + '/' + this.drawer.tenant.site_id + '/quota', {
          method: 'PUT', body: JSON.stringify(payload) });
        this.replaceTenant(body.tenant);
        this.flash(body.message);
        await this.refresh(false, true);
      } catch (e) { this.flash(e.message, false); }
      finally { this.drawer.busy = false; }
    },

    // ─── Presentation (full class strings so the CSS build keeps them) ───
    levelFor(pct) {
      if (pct === null || pct === undefined) return 'unknown';
      if (pct >= this.data.thresholds.critical_percent) return 'critical';
      if (pct >= this.data.thresholds.warning_percent) return 'warning';
      return 'normal';
    },
    dash(pct) { const p = Math.max(0, Math.min(100, pct ?? 0)); return p + ' ' + (100 - p); },
    labelFor(l) { return { normal: 'عادی', warning: 'هشدار', critical: 'بحرانی', unknown: 'نامشخص' }[l] || l; },
    badgeFor(l) { return { normal: 'bg-emerald-50 text-emerald-800 ring-emerald-200', warning: 'bg-amber-50 text-amber-800 ring-amber-200',
                           critical: 'bg-rose-50 text-rose-800 ring-rose-200', unknown: 'bg-gray-50 text-gray-700 ring-gray-200' }[l]; },
    dotFor(l)   { return { normal: 'bg-emerald-500', warning: 'bg-amber-500', critical: 'bg-rose-600', unknown: 'bg-gray-400' }[l]; },
    barFor(l)   { return { normal: 'bg-emerald-500', warning: 'bg-amber-500', critical: 'bg-rose-600', unknown: 'bg-gray-300' }[l]; },
    strokeFor(l){ return { normal: 'stroke-emerald-500', warning: 'stroke-amber-500', critical: 'stroke-rose-600', unknown: 'stroke-gray-300' }[l]; },
    statusLabel(s) { return { completed: 'انجام شد', partial: 'ناقص', failed: 'ناموفق', running: 'در حال اجرا' }[s] || s; },
    modeLabel(m) { return m === 'dry_run' ? 'پیش‌نمایش' : 'پاک‌سازی'; },
    triggerLabel(t) { return t === 'auto_agent' ? 'خودکار' : 'مدیر'; },
    profileLabel(p) { return { routine: 'روزمره', aggressive: 'شدید (بحرانی)' }[p] || p; },
  };
}
</script>
@endsection
