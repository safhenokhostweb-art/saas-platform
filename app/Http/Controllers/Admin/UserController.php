<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Services\ActivityLogger;
use App\UseCases\User\BulkDeleteUserUseCase;
use App\UseCases\User\BulkRestoreUserUseCase;
use App\UseCases\User\BulkToggleUserStatusUseCase;
use App\UseCases\User\CreateUserUseCase;
use App\UseCases\User\DeleteUserUseCase;
use App\UseCases\User\SearchUsersUseCase;
use App\UseCases\User\UpdateUserUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @file user-controller.php
 * قانون ۱: معماری لایه‌ها - کنترلر فقط درخواست را دریافت، UseCase را صدا بزند و پاسخ را برگرداند
 * قانون ۴: اعتبارسنجی ورودی - استفاده از FormRequest
 * قانون ۶: پاسخ‌دهی - Format خروجی ثابت
 */
class UserController extends Controller
{
    public function __construct(
        private CreateUserUseCase $createUserUseCase,
        private UpdateUserUseCase $updateUserUseCase,
        private DeleteUserUseCase $deleteUserUseCase,
        private BulkToggleUserStatusUseCase $bulkToggleUserStatusUseCase,
        private BulkDeleteUserUseCase $bulkDeleteUserUseCase,
        private BulkRestoreUserUseCase $bulkRestoreUserUseCase,
        private SearchUsersUseCase $searchUsersUseCase,
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * نمایش لیست کاربران با جستجوی پیشرفته
     */
    public function index(Request $request)
    {
        $filters = $request->only(['mobile', 'email', 'name', 'status', 'role', 'group_id', 'tag_id', 'two_factor', 'created_from', 'created_to']);
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');

        $users = $this->searchUsersUseCase->execute($filters, $sortBy, $sortDir);
        $stats = $this->userRepository->getStats();
        $roles = Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'filters', 'sortBy', 'sortDir', 'stats', 'roles'));
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
    public function store(StoreUserRequest $request)
    {
        $user = $this->createUserUseCase->execute($request->validated());

        return redirect()->route('admin.users.index')
            ->with('success', "کاربر {$user->mobile} با موفقیت ایجاد شد.");
    }

    /**
     * نمایش فرم ویرایش کاربر
     */
    public function edit(User $user)
    {
        $user->load(['profile', 'roles', 'groups', 'tags']);
        $roles = Role::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * به‌روزرسانی کاربر
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->updateUserUseCase->execute($user, $request->validated());

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
        $this->userRepository->clearCache();

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
        $count = $this->bulkToggleUserStatusUseCase->execute($request->user_ids, $active);

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

        $count = $this->bulkDeleteUserUseCase->execute($request->user_ids);

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

        $count = $this->bulkRestoreUserUseCase->execute($request->user_ids);

        return back()->with('success', "{$count} کاربر بازیابی شدند.");
    }

    /**
     * Export کاربران به CSV
     */
    public function exportCsv(Request $request)
    {
        $filters = $request->only(['mobile', 'email', 'status']);
        $users = $this->userRepository->getAllForExport($filters);

        $filename = 'users_export_' . now()->format('Ymd_His') . '.csv';
        $path = storage_path("app/exports/{$filename}");
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'w');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, ['#', 'موبایل', 'ایمیل', 'نام', 'وضعیت', 'ادمین', '2FA', 'گروه‌ها', 'برچسب‌ها', 'تاریخ ثبت‌نام']);

        foreach ($users as $user) {
            fputcsv($handle, [
                $user->id,
                $user->mobile,
                $user->email ?? '',
                $user->profile?->full_name ?? '',
                $user->is_active ? 'فعال' : 'غیرفعال',
                $user->is_admin ? 'بله' : 'خیر',
                $user->hasTwoFactorEnabled() ? 'فعال' : 'غیرفعال',
                $user->groups->pluck('name')->implode('، '),
                $user->tags->pluck('name')->implode('، '),
                $user->created_at->format('Y-m-d H:i'),
            ]);
        }

        fclose($handle);

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

        if (!file_exists($fullPath)) {
            return back()->with('error', 'فایل یافت نشد.');
        }

        $result = ['imported' => 0, 'failed' => []];
        $handle = fopen($fullPath, 'r');
        $header = fgetcsv($handle);

        \Illuminate\Support\Facades\DB::transaction(function () use ($handle, &$result) {
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = [
                        'mobile'    => $row[1] ?? '',
                        'email'     => $row[2] ?? '',
                        'first_name'=> $row[3] ?? '',
                        'last_name' => '',
                        'is_active' => ($row[4] ?? 'فعال') === 'فعال',
                    ];

                    if (empty($data['mobile'])) {
                        continue;
                    }

                    $this->createUserUseCase->execute($data);
                    $result['imported']++;
                } catch (\Exception $e) {
                    $result['failed'][] = $row[1] ?? 'unknown';
                    \Illuminate\Support\Facades\Log::warning("Import user failed: {$e->getMessage()}");
                }
            }
        });

        fclose($handle);

        $message = "{$result['imported']} کاربر با موفقیت وارد شدند.";
        if (!empty($result['failed'])) {
            $message .= ' ' . count($result['failed']) . ' مورد ناموفق: ' . implode(', ', array_slice($result['failed'], 0, 5));
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }
}
