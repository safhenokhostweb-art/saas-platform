{{-- فیلترها و جستجوی پیشرفته --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">

    {{-- ردیف اول: جستجوی سریع + دکمه‌ها --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- جستجو با موبایل --}}
        <div class="relative flex-1 min-w-40">
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <input type="text" x-model="filters.mobile"
                   class="form-control pr-9 text-sm"
                   placeholder="جستجو با موبایل...">
        </div>

        {{-- جستجو با نام --}}
        <div class="relative flex-1 min-w-40">
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <input type="text" x-model="filters.name"
                   class="form-control pr-9 text-sm"
                   placeholder="جستجو با نام...">
        </div>

        {{-- دکمه فیلترهای پیشرفته --}}
        <button type="button"
                @click="showAdvancedFilters = !showAdvancedFilters"
                class="flex items-center gap-1.5 text-sm px-4 py-2 rounded-xl border transition-all"
                :class="showAdvancedFilters
                    ? 'bg-indigo-50 border-indigo-200 text-indigo-700'
                    : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span>فیلترها</span>
            <span x-show="activeFilterCount > 0"
                  x-text="activeFilterCount"
                  class="bg-indigo-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold leading-none"></span>
        </button>

        <div class="mr-auto flex flex-wrap gap-2">
            <a href="{{ route('admin.users.create') }}"
               class="btn btn-primary text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                کاربر جدید
            </a>
            <button id="export-csv-btn"
                    data-url="{{ route('admin.users.export-csv') }}"
                    @click="exportCSV()"
                    class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                خروجی CSV
            </button>
            <a href="{{ route('admin.users.import') }}"
               class="btn btn-outline-secondary text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                ورود CSV
            </a>
        </div>
    </div>

    {{-- ردیف دوم: فیلترهای پیشرفته (قابل باز/بسته شدن) --}}
    <div x-show="showAdvancedFilters"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         style="display:none">

        <div class="pt-4 border-t border-gray-100 space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">

                {{-- وضعیت --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">وضعیت</label>
                    <select x-model="filters.status" class="form-select text-sm">
                        <option value="">همه</option>
                        <option value="active">فعال</option>
                        <option value="inactive">غیرفعال</option>
                        <option value="locked">قفل شده</option>
                    </select>
                </div>

                {{-- نقش --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">نقش</label>
                    <select x-model="filters.role" class="form-select text-sm">
                        <option value="">همه نقش‌ها</option>
                        @if(isset($roles))
                            @foreach($roles as $role)
                                <option value="{{ $role->slug }}">{{ $role->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                {{-- احراز دومرحله‌ای --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">احراز ۲ مرحله‌ای</label>
                    <select x-model="filters.two_factor" class="form-select text-sm">
                        <option value="">همه</option>
                        <option value="enabled">فعال</option>
                        <option value="disabled">غیرفعال</option>
                    </select>
                </div>

                {{-- حذف شده‌ها --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">نمایش</label>
                    <select x-model="filters.trashed" class="form-select text-sm">
                        <option value="0">فقط فعال</option>
                        <option value="1">فقط حذف‌شده</option>
                        <option value="all">همه</option>
                    </select>
                </div>

                {{-- از تاریخ --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">از تاریخ عضویت</label>
                    <input type="date" x-model="filters.created_from"
                           class="form-control text-sm">
                </div>

                {{-- تا تاریخ --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">تا تاریخ عضویت</label>
                    <input type="date" x-model="filters.created_to"
                           class="form-control text-sm">
                </div>
            </div>

            {{-- دکمه‌های عمل --}}
            <div class="flex items-center gap-2 pt-1">
                <button type="button" @click="search()"
                        class="btn btn-primary btn-sm text-sm flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    اعمال فیلترها
                </button>
                <button type="button" @click="resetFilters()"
                        class="btn btn-outline-secondary btn-sm text-sm flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    پاک کردن همه
                </button>
            </div>
        </div>
    </div>

    {{-- فیلترهای فعال به‌صورت badge --}}
    <div x-show="filters.mobile || filters.name || hasActiveFilters"
         class="flex flex-wrap gap-2"
         style="display:none">

        <template x-if="filters.mobile">
            <span class="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full px-2.5 py-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                موبایل: <span x-text="filters.mobile" class="font-medium"></span>
                <button @click="filters.mobile = ''; search()" class="mr-0.5 text-indigo-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.name">
            <span class="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full px-2.5 py-1">
                نام: <span x-text="filters.name" class="font-medium"></span>
                <button @click="filters.name = ''; search()" class="mr-0.5 text-indigo-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.status">
            <span class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-200 rounded-full px-2.5 py-1">
                وضعیت:
                <span class="font-medium" x-text="filters.status === 'active' ? 'فعال' : filters.status === 'inactive' ? 'غیرفعال' : 'قفل شده'"></span>
                <button @click="filters.status = ''; search()" class="mr-0.5 text-blue-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.role">
            <span class="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 border border-purple-200 rounded-full px-2.5 py-1">
                نقش: <span x-text="filters.role" class="font-medium"></span>
                <button @click="filters.role = ''; search()" class="mr-0.5 text-purple-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.two_factor">
            <span class="inline-flex items-center gap-1 text-xs bg-violet-50 text-violet-700 border border-violet-200 rounded-full px-2.5 py-1">
                ۲FA:
                <span class="font-medium" x-text="filters.two_factor === 'enabled' ? 'فعال' : 'غیرفعال'"></span>
                <button @click="filters.two_factor = ''; search()" class="mr-0.5 text-violet-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.created_from">
            <span class="inline-flex items-center gap-1 text-xs bg-gray-50 text-gray-700 border border-gray-200 rounded-full px-2.5 py-1">
                از: <span x-text="filters.created_from" class="font-medium"></span>
                <button @click="filters.created_from = ''; search()" class="mr-0.5 text-gray-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.created_to">
            <span class="inline-flex items-center gap-1 text-xs bg-gray-50 text-gray-700 border border-gray-200 rounded-full px-2.5 py-1">
                تا: <span x-text="filters.created_to" class="font-medium"></span>
                <button @click="filters.created_to = ''; search()" class="mr-0.5 text-gray-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>

        <template x-if="filters.trashed && filters.trashed !== '0'">
            <span class="inline-flex items-center gap-1 text-xs bg-amber-50 text-amber-700 border border-amber-200 rounded-full px-2.5 py-1">
                <span x-text="filters.trashed === '1' ? 'فقط حذف‌شده' : 'همه کاربران'"></span>
                <button @click="filters.trashed = '0'; search()" class="mr-0.5 text-amber-400 hover:text-red-500 font-bold leading-none">×</button>
            </span>
        </template>
    </div>

</div>
