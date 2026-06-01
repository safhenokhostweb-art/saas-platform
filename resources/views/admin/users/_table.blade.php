{{-- جدول کاربران --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- نوار عملیات گروهی --}}
    <div x-show="selectedIds.length > 0" x-transition
         class="px-4 py-3 bg-indigo-50 border-b border-indigo-100 flex flex-wrap items-center gap-2"
         style="display:none">
        <span class="text-sm font-semibold text-indigo-700 flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span x-text="selectedIds.length"></span> کاربر انتخاب شده
        </span>
        <button type="button"
                class="inline-flex items-center gap-1 btn btn-sm bg-green-500 text-white hover:bg-green-600 text-xs"
                @click="openBulkAction('activate')">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            فعال‌سازی
        </button>
        <button type="button"
                class="inline-flex items-center gap-1 btn btn-sm bg-gray-500 text-white hover:bg-gray-600 text-xs"
                @click="openBulkAction('deactivate')">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            غیرفعال‌سازی
        </button>
        <button type="button"
                class="inline-flex items-center gap-1 btn btn-sm bg-red-500 text-white hover:bg-red-600 text-xs"
                @click="openBulkAction('delete')">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            حذف گروهی
        </button>
        @if(request()->get('trashed') == '1')
        <button type="button"
                class="inline-flex items-center gap-1 btn btn-sm bg-amber-500 text-white hover:bg-amber-600 text-xs"
                @click="openBulkAction('restore')">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            بازیابی
        </button>
        @endif
        <button type="button"
                class="btn btn-sm btn-outline-secondary text-xs mr-auto"
                @click="selectedIds = []; selectAll = false">
            لغو انتخاب
        </button>
    </div>

    {{-- جدول --}}
    <div class="overflow-x-auto">
        <table class="w-full text-right text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                    <th class="p-3 w-10">
                        <input type="checkbox" class="form-check-input"
                               :checked="selectAll"
                               @change="toggleSelectAll()">
                    </th>
                    <th class="p-3 font-semibold cursor-pointer hover:text-indigo-600 select-none whitespace-nowrap"
                        @click="toggleSort('id')">
                        <span class="flex items-center gap-1">
                            #
                            <span x-show="sortBy === 'id'" x-text="sortDir === 'asc' ? '↑' : '↓'" class="text-indigo-500"></span>
                        </span>
                    </th>
                    <th class="p-3 font-semibold">آواتار</th>
                    <th class="p-3 font-semibold cursor-pointer hover:text-indigo-600 select-none whitespace-nowrap"
                        @click="toggleSort('mobile')">
                        <span class="flex items-center gap-1">
                            موبایل
                            <span x-show="sortBy === 'mobile'" x-text="sortDir === 'asc' ? '↑' : '↓'" class="text-indigo-500"></span>
                        </span>
                    </th>
                    <th class="p-3 font-semibold">نام / پروفایل</th>
                    <th class="p-3 font-semibold">نقش‌ها</th>
                    <th class="p-3 font-semibold cursor-pointer hover:text-indigo-600 select-none whitespace-nowrap"
                        @click="toggleSort('last_login_at')">
                        <span class="flex items-center gap-1">
                            آخرین ورود
                            <span x-show="sortBy === 'last_login_at'" x-text="sortDir === 'asc' ? '↑' : '↓'" class="text-indigo-500"></span>
                        </span>
                    </th>
                    <th class="p-3 font-semibold cursor-pointer hover:text-indigo-600 select-none whitespace-nowrap"
                        @click="toggleSort('created_at')">
                        <span class="flex items-center gap-1">
                            تاریخ عضویت
                            <span x-show="sortBy === 'created_at'" x-text="sortDir === 'asc' ? '↑' : '↓'" class="text-indigo-500"></span>
                        </span>
                    </th>
                    <th class="p-3 font-semibold">وضعیت</th>
                    <th class="p-3 font-semibold text-center">۲FA</th>
                    <th class="p-3 font-semibold">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($users as $user)
                    @php
                        $isSelected = false; // Alpine handles this
                        $avatarText = mb_substr($user->profile?->full_name ?? $user->mobile, 0, 1);
                        $avatarColors = ['bg-indigo-100 text-indigo-700', 'bg-green-100 text-green-700',
                                         'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700',
                                         'bg-rose-100 text-rose-700', 'bg-teal-100 text-teal-700'];
                        $colorClass = $avatarColors[$user->id % count($avatarColors)];
                    @endphp
                    <tr class="hover:bg-gray-50/70 transition-colors group"
                        :class="{'bg-indigo-50/40': selectedIds.includes('{{ $user->id }}')}">

                        {{-- Checkbox --}}
                        <td class="p-3">
                            <input type="checkbox" class="user-checkbox form-check-input"
                                   value="{{ $user->id }}"
                                   :checked="selectedIds.includes('{{ $user->id }}')"
                                   @change="toggleUser('{{ $user->id }}')">
                        </td>

                        {{-- شناسه --}}
                        <td class="p-3 text-gray-400 font-mono text-xs">
                            {{ \App\Helpers\NumberHelper::toPersianNumbers('#' . $user->id) }}
                        </td>

                        {{-- آواتار --}}
                        <td class="p-3">
                            @if($user->profile?->avatar)
                                @php $avatarSrc = str_starts_with($user->profile->avatar, 'http') ? $user->profile->avatar : url($user->profile->avatar); @endphp
                                <img src="{{ $avatarSrc }}"
                                     class="w-9 h-9 rounded-full object-cover ring-2 ring-white shadow-sm" alt="">
                            @else
                                <div class="w-9 h-9 rounded-full {{ $colorClass }} flex items-center justify-center font-bold text-sm ring-2 ring-white shadow-sm">
                                    {{ $avatarText }}
                                </div>
                            @endif
                        </td>

                        {{-- موبایل --}}
                        <td class="p-3 font-medium text-gray-800 tabular-nums">
                            {{ \App\Helpers\NumberHelper::toPersianNumbers($user->mobile) }}
                        </td>

                        {{-- نام / پروفایل --}}
                        <td class="p-3">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-800">{{ $user->profile?->full_name ?? '—' }}</span>
                                @if($user->customer)
                                    <span class="text-xs text-gray-400 mt-0.5">
                                        {{ $user->customer->company_name ?? $user->customer->name ?? '' }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- نقش‌ها --}}
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <span class="inline-flex items-center text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-full">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                                @if($user->roles->isEmpty())
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </div>
                        </td>

                        {{-- آخرین ورود --}}
                        <td class="p-3 text-xs text-gray-500 tabular-nums whitespace-nowrap">
                            @if($user->last_login_at)
                                <div>{{ \App\Helpers\Jalali::formatFa($user->last_login_at, 'Y/m/d') }}</div>
                                <div class="text-gray-400">{{ \App\Helpers\NumberHelper::toPersianNumbers($user->last_login_at->format('H:i')) }}</div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- تاریخ عضویت --}}
                        <td class="p-3 text-xs text-gray-500 tabular-nums whitespace-nowrap">
                            @if($user->created_at)
                                <div>{{ \App\Helpers\Jalali::formatFa($user->created_at, 'Y/m/d') }}</div>
                                <div class="text-gray-400">{{ \App\Helpers\NumberHelper::toPersianNumbers($user->created_at->format('H:i')) }}</div>
                            @else
                                —
                            @endif
                        </td>

                        {{-- وضعیت --}}
                        <td class="p-3">
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold transition-all
                                                {{ $user->is_active
                                                    ? 'bg-green-50 text-green-700 border border-green-200 hover:bg-green-100'
                                                    : 'bg-gray-100 text-gray-500 border border-gray-200 hover:bg-gray-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                    </button>
                                </form>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs text-gray-400 bg-gray-50 border border-gray-200 px-2.5 py-1 rounded-lg">
                                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                                    شما
                                </span>
                            @endif
                        </td>

                        {{-- ۲FA --}}
                        <td class="p-3 text-center">
                            @if($user->hasTwoFactorEnabled())
                                <span class="inline-flex items-center gap-1 text-xs bg-violet-50 text-violet-700 border border-violet-100 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    فعال
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>

                        {{-- عملیات --}}
                        <td class="p-3 relative" data-row-dropdown>
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-colors"
                                   title="ویرایش">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>

                                @if($user->is_admin)
                                    <span class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-md font-medium">ادمین</span>
                                @endif

                                <button @click.stop="toggleRowDropdown('{{ $user->id }}')"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    </svg>
                                </button>

                                {{-- Dropdown منو --}}
                                <div x-show="openRowId === '{{ $user->id }}'"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute left-0 top-full mt-1 w-52 bg-white rounded-xl shadow-lg border border-gray-200 z-50 py-1 text-sm overflow-hidden"
                                     style="display:none"
                                     @click.outside="closeRowDropdown()">

                                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full {{ $colorClass }} flex items-center justify-center text-xs font-bold">
                                            {{ $avatarText }}
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-gray-700">
                                                {{ \App\Helpers\NumberHelper::toPersianNumbers($user->mobile) }}
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                {{ \App\Helpers\NumberHelper::toPersianNumbers('#' . $user->id) }}
                                            </div>
                                        </div>
                                    </div>

                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 text-gray-700 transition-colors">
                                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        ویرایش کاربر
                                    </a>

                                    <form action="{{ route('admin.users.toggle', $user) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="flex items-center gap-2 w-full text-right px-3 py-2 hover:bg-gray-50 text-gray-700 transition-colors">
                                            @if($user->is_active)
                                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                                غیرفعال کردن
                                            @else
                                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                فعال کردن
                                            @endif
                                        </button>
                                    </form>

                                    @php $hasCustomer = !is_null($user->customer); @endphp
                                    @if($hasCustomer)
                                        <a href="{{ route('admin.customers.edit', $user->customer) }}"
                                           class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 text-gray-700 transition-colors">
                                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            پروفایل مشتری
                                        </a>
                                    @endif

                                    @if(auth()->id() !== $user->id)
                                        <hr class="my-1 border-gray-100">
                                        <button @click.stop="openDeleteModal('{{ $user->id }}')"
                                                class="flex items-center gap-2 w-full text-right px-3 py-2 text-red-600 hover:bg-red-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            حذف کاربر
                                        </button>
                                    @endif
                                </div>

                                <form id="delete-form-{{ $user->id }}"
                                      action="{{ route('admin.users.bulk-delete') }}" method="POST" class="hidden">
                                    @csrf
                                    <input type="hidden" name="user_ids[]" value="{{ $user->id }}">
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <p class="text-gray-500 font-medium">کاربری یافت نشد</p>
                                <p class="text-gray-400 text-sm">فیلترهای جستجو را تغییر دهید یا کاربر جدید ایجاد کنید.</p>
                                <a href="{{ route('admin.users.create') }}" class="btn btn-primary text-sm mt-1">
                                    + ایجاد کاربر جدید
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- فوتر: صفحه‌بندی --}}
    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 text-sm text-gray-500">
            <span>
                نمایش
                <span class="font-medium text-gray-700">{{ \App\Helpers\NumberHelper::toPersianNumbers($users->firstItem() ?? 0) }}</span>
                تا
                <span class="font-medium text-gray-700">{{ \App\Helpers\NumberHelper::toPersianNumbers($users->lastItem() ?? 0) }}</span>
                از
                <span class="font-semibold text-indigo-600">{{ \App\Helpers\NumberHelper::formatFa($users->total()) }}</span>
                کاربر
            </span>
            <div class="flex items-center gap-1.5">
                <label class="text-xs text-gray-400">تعداد در صفحه:</label>
                <select @change="changePageSize($event)"
                        class="form-select text-xs py-1 w-16 rounded-lg"
                        x-init="$el.value = pageSize">
                    <template x-for="opt in pageSizeOptions" :key="opt">
                        <option :value="opt" x-text="opt"></option>
                    </template>
                </select>
            </div>
        </div>
        <div class="text-sm">
            {{ $users->appends(request()->query())->links() }}
        </div>
    </div>
</div>
