<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\UserService;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Rules\IranianMobile;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * نمایش لیست کاربران با جستجوی پیشرفته
     */
    public function index(Request $request)
    {
        $filters = $request->only(['mobile', 'email', 'name', 'status', 'role', 'created_from', 'created_to']);
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');

        $users = $this->userService->search($filters, $sortBy, $sortDir);

        return view('admin.users.index', compact('users', 'filters', 'sortBy', 'sortDir'));
    }

    /**
     * نمایش فرم ایجاد کاربر جدید
     */
    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * ذخیره کاربر جدید
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile'     => ['required', 'string', 'unique:users,mobile', new IranianMobile],
            'email'      => 'nullable|email|unique:users,email',
            'password'   => 'nullable|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'is_active'  => 'boolean',
            'is_admin'   => 'boolean',
            'roles'      => 'nullable|array',
            'roles.*'    => 'exists:roles,id',
        ], [
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'email.unique'  => 'این ایمیل قبلاً ثبت شده است.',
            'password.min'  => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = $this->userService->create($validator->validated());

        return redirect()->route('admin.users.index')
            ->with('success', "کاربر {$user->mobile} با موفقیت ایجاد شد.");
    }

    /**
     * نمایش فرم ویرایش کاربر
     */
    public function edit(User $user)
    {
        $user->load(['profile', 'roles']);
        $roles = Role::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * به‌روزرسانی کاربر
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'mobile'     => ['required', 'string', 'unique:users,mobile,' . $user->id, new IranianMobile],
            'email'      => 'nullable|email|unique:users,email,' . $user->id,
            'password'   => 'nullable|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'is_active'  => 'boolean',
            'is_admin'   => 'boolean',
            'roles'      => 'nullable|array',
            'roles.*'    => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->userService->update($user, $validator->validated());

        return redirect()->route('admin.users.index')
            ->with('success', "کاربر {$user->mobile} با موفقیت به‌روزرسانی شد.");
    }

    /**
     * تغییر وضعیت تک‌کاربر (فعال/غیرفعال)
     */
    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'نمی‌توانید اکانت خودتان را غیرفعال کنید.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $message = $user->is_active ? 'کاربر فعال شد.' : 'کاربر غیرفعال شد.';
        ActivityLogger::log('user.toggled', $message . ' (موبایل: ' . $user->mobile . ')', $user);
        $this->userService->clearCache();

        return back()->with('success', $message);
    }

    /**
     * Bulk تغییر وضعیت کاربران
     */
    public function bulkToggle(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'action'     => 'required|in:activate,deactivate',
        ]);

        $active = $request->action === 'activate';
        $count = $this->userService->bulkToggleActive($request->user_ids, $active);

        $msg = $active ? "{$count} کاربر فعال شدند." : "{$count} کاربر غیرفعال شدند.";
        return back()->with('success', $msg);
    }

    /**
     * Bulk حذف نرم کاربران
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $count = $this->userService->bulkSoftDelete($request->user_ids);

        return back()->with('success', "{$count} کاربر حذف شدند.");
    }

    /**
     * Bulk بازیابی کاربران حذف‌شده
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $count = $this->userService->bulkRestore($request->user_ids);

        return back()->with('success', "{$count} کاربر بازیابی شدند.");
    }

    /**
     * Export کاربران به CSV
     */
    public function exportCsv(Request $request)
    {
        $filters = $request->only(['mobile', 'email', 'status']);
        $filename = $this->userService->exportCsv($filters, 'created_at', 'desc');

        return Storage::disk('local')->download("exports/{$filename}");
    }

    /**
     * نمایش فرم Import کاربران
     */
    public function showImportForm()
    {
        return view('admin.users.import');
    }

    /**
     * Import کاربران از CSV
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $path = $request->file('file')->store('imports');
        $fullPath = storage_path("app/{$path}");

        $result = $this->userService->importCsv($fullPath);

        $message = "{$result['imported']} کاربر با موفقیت وارد شدند.";
        if (!empty($result['failed'])) {
            $message .= ' ' . count($result['failed']) . ' مورد ناموفق: ' . implode(', ', $result['failed']);
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }
}
