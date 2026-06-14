@extends('layouts.admin')
@section('title', 'افزودن سایت جدید — Wizard')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6" x-data="siteWizard()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.sites.index') }}" class="flex items-center gap-1.5 text-slate-500 hover:text-slate-800 text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            سایت‌ها
        </a>
        <span class="text-slate-300">/</span>
        <h1 class="text-xl font-bold text-slate-800">افزودن سایت جدید</h1>
    </div>

    {{-- Progress Steps --}}
    <div class="flex items-center gap-0 mb-8 bg-white rounded-xl border border-slate-200 p-1 shadow-sm">
        @foreach([['num'=>1,'label'=>'اطلاعات اصلی','icon'=>'🏢'], ['num'=>2,'label'=>'ماژول‌ها','icon'=>'🧩'], ['num'=>3,'label'=>'پایگاه داده','icon'=>'🗄️'], ['num'=>4,'label'=>'تأیید و ایجاد','icon'=>'✅']] as $s)
        <button
            @click="step > {{ $s['num'] }} ? (step = {{ $s['num'] }}) : null"
            :class="step === {{ $s['num'] }} ? 'bg-indigo-600 text-white shadow-sm' : (step > {{ $s['num'] }} ? 'text-emerald-600 bg-emerald-50 cursor-pointer' : 'text-slate-400 bg-transparent cursor-default')"
            class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium transition"
        >
            <span>{{ $s['icon'] }}</span>
            <span class="hidden sm:inline">{{ $s['label'] }}</span>
            <span class="sm:hidden">{{ $s['num'] }}</span>
            <svg x-show="step > {{ $s['num'] }}" class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
        </button>
        @endforeach
    </div>

    {{-- Steps Container --}}
    <form method="POST" action="{{ route('admin.sites.store') }}" id="wizard-form">
        @csrf

        {{-- Step 1: اطلاعات اصلی --}}
        <div x-show="step === 1" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">🏢 اطلاعات اصلی سایت</h2>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">نام سایت <span class="text-red-500">*</span></label>
                <input type="text" name="title" x-model="form.title" required
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none"
                    placeholder="مثال: فروشگاه آنلاین نمونه">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">دامنه</label>
                <input type="text" name="domain" x-model="form.domain"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none ltr"
                    placeholder="example.com">
                <p class="text-xs text-slate-400 mt-1">بدون https و www</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">مشتری <span class="text-red-500">*</span></label>
                <select name="customer_id" x-model="form.customer_id" required
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none bg-white">
                    <option value="">انتخاب مشتری...</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} — {{ $c->mobile }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">شرکت هاستینگ</label>
                <input type="text" name="hosting_company" x-model="form.hosting_company"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-300 outline-none"
                    placeholder="آروان / لایم‌هوست / ...">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">سطح پشتیبانی</label>
                    <select name="support_level" x-model="form.support_level" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm outline-none bg-white focus:ring-2 focus:ring-indigo-300">
                        <option value="basic">Basic</option>
                        <option value="pro">Pro</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                <div class="flex items-end pb-0.5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-300">
                        <span class="text-sm font-medium text-slate-700">سایت فعال باشد</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Step 2: ماژول‌ها --}}
        <div x-show="step === 2" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2 mb-4">🧩 انتخاب ماژول‌های سایت</h2>
            <p class="text-xs text-slate-500 mb-4">ماژول‌هایی که می‌خواهید برای این سایت فعال شوند را انتخاب کنید.</p>

            @forelse($modulesByCategory as $category => $catModules)
            <div class="mb-4">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 border-b border-slate-100 pb-1">{{ $category ?: 'عمومی' }}</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($catModules as $mod)
                    <label class="flex items-center gap-2.5 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition"
                           :class="form.modules.includes({{ $mod->id }}) ? 'border-indigo-400 bg-indigo-50' : ''">
                        <input type="checkbox" name="modules[]" value="{{ $mod->id }}"
                               x-model="form.modules" class="w-3.5 h-3.5 rounded text-indigo-600 focus:ring-indigo-300 border-slate-300">
                        <span class="text-xs font-medium text-slate-700 truncate">{{ $mod->title }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-400 text-center py-6">هیچ ماژول فعالی یافت نشد.</p>
            @endforelse
        </div>

        {{-- Step 3: پایگاه داده --}}
        <div x-show="step === 3" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">🗄️ اطلاعات پایگاه داده</h2>
            <div class="p-3 rounded-lg bg-blue-50 border border-blue-200 text-xs text-blue-700">
                این اطلاعات برای اتصال پلتفرم به دیتابیس سایت مشتری استفاده می‌شود.
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">نام دیتابیس</label>
                    <input type="text" name="db_name" x-model="form.db_name"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm outline-none ltr focus:ring-2 focus:ring-indigo-300"
                        placeholder="customer_db">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">هاست</label>
                    <input type="text" name="db_host" x-model="form.db_host"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm outline-none ltr focus:ring-2 focus:ring-indigo-300"
                        placeholder="127.0.0.1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">نام کاربری</label>
                    <input type="text" name="db_user" x-model="form.db_user"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm outline-none ltr focus:ring-2 focus:ring-indigo-300"
                        placeholder="db_user">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">رمز عبور</label>
                    <input type="password" name="db_password" x-model="form.db_password"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm outline-none ltr focus:ring-2 focus:ring-indigo-300"
                        placeholder="••••••••">
                </div>
            </div>
        </div>

        {{-- Step 4: تأیید --}}
        <div x-show="step === 4" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">✅ تأیید و ایجاد سایت</h2>

            <div class="divide-y divide-slate-100 rounded-lg border border-slate-200 overflow-hidden text-sm">
                <div class="flex px-4 py-2.5 bg-slate-50">
                    <span class="w-40 font-medium text-slate-500">نام سایت</span>
                    <span class="font-semibold text-slate-800" x-text="form.title || '—'"></span>
                </div>
                <div class="flex px-4 py-2.5">
                    <span class="w-40 font-medium text-slate-500">دامنه</span>
                    <span class="text-slate-700 ltr" x-text="form.domain || '—'"></span>
                </div>
                <div class="flex px-4 py-2.5 bg-slate-50">
                    <span class="w-40 font-medium text-slate-500">مشتری</span>
                    <span class="text-slate-700" x-text="customerName()"></span>
                </div>
                <div class="flex px-4 py-2.5">
                    <span class="w-40 font-medium text-slate-500">ماژول‌ها</span>
                    <span class="text-slate-700" x-text="form.modules.length + ' ماژول انتخاب‌شده'"></span>
                </div>
                <div class="flex px-4 py-2.5 bg-slate-50">
                    <span class="w-40 font-medium text-slate-500">پایگاه داده</span>
                    <span class="ltr text-slate-700" x-text="form.db_name ? (form.db_user + '@' + (form.db_host || 'localhost') + '/' + form.db_name) : 'تنظیم نشده'"></span>
                </div>
                <div class="flex px-4 py-2.5">
                    <span class="w-40 font-medium text-slate-500">وضعیت</span>
                    <span :class="form.is_active ? 'text-emerald-700' : 'text-slate-500'" x-text="form.is_active ? 'فعال' : 'غیرفعال'"></span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition shadow-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ایجاد سایت جدید
                </button>
            </div>
        </div>

    </form>

    {{-- Navigation Buttons --}}
    <div class="flex justify-between mt-4">
        <button
            x-show="step > 1"
            @click="step--"
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            مرحله قبل
        </button>
        <div x-show="step === 1" class="mr-auto"></div>

        <button
            x-show="step < 4"
            @click="nextStep()"
            class="flex items-center gap-1.5 px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition"
        >
            مرحله بعد
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->name]));

function siteWizard() {
    return {
        step: 1,
        form: {
            title:           '',
            domain:          '',
            customer_id:     '',
            hosting_company: '',
            support_level:   'basic',
            is_active:       true,
            modules:         [],
            db_name:         '',
            db_host:         '127.0.0.1',
            db_user:         '',
            db_password:     '',
        },

        init() {},

        nextStep() {
            if (this.step === 1 && !this.validate1()) return;
            if (this.step < 4) this.step++;
        },

        validate1() {
            if (!this.form.title.trim()) {
                alert('نام سایت الزامی است.');
                return false;
            }
            if (!this.form.customer_id) {
                alert('انتخاب مشتری الزامی است.');
                return false;
            }
            return true;
        },

        customerName() {
            const c = CUSTOMERS.find(x => String(x.id) === String(this.form.customer_id));
            return c ? c.name : '—';
        },
    };
}
</script>
@endpush
