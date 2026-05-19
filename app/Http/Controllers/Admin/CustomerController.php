<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Services\ActivityLogger;
use App\UseCases\Customer\BulkDeleteCustomerUseCase;
use App\UseCases\Customer\BulkRestoreCustomerUseCase;
use App\UseCases\Customer\CreateCustomerUseCase;
use App\UseCases\Customer\DeleteCustomerUseCase;
use App\UseCases\Customer\SearchCustomersUseCase;
use App\UseCases\Customer\UpdateCustomerUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @file customer-controller.php
 * قانون ۱: معماری لایه‌ها - کنترلر فقط درخواست را دریافت کرده و UseCase را صدا می‌زند
 * قانون ۴: اعتبارسنجی - استفاده از FormRequest معادل
 * قانون ۳: Soft Delete در UseCase رعایت شده
 */
class CustomerController extends Controller
{
    public function __construct(
        private CreateCustomerUseCase $createCustomerUseCase,
        private UpdateCustomerUseCase $updateCustomerUseCase,
        private DeleteCustomerUseCase $deleteCustomerUseCase,
        private BulkDeleteCustomerUseCase $bulkDeleteCustomerUseCase,
        private BulkRestoreCustomerUseCase $bulkRestoreCustomerUseCase,
        private SearchCustomersUseCase $searchCustomersUseCase,
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    /**
     * نمایش لیست مشتریان با جستجوی پیشرفته
     */
    public function index(Request $request)
    {
        $filters = $request->only(['name', 'mobile', 'email', 'reseller_id', 'has_site', 'created_from', 'created_to', 'q']);
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');

        $customers = $this->searchCustomersUseCase->execute($filters, $sortBy, $sortDir);
        $resellers = Reseller::with('user.profile')->orderBy('company_name')->get();
        $stats = $this->customerRepository->getStats();

        return view('admin.customers.index', compact('customers', 'filters', 'sortBy', 'sortDir', 'resellers', 'stats'));
    }

    /**
     * نمایش فرم ایجاد مشتری
     */
    public function create()
    {
        $users = User::with('profile')->orderBy('mobile')->get();
        $resellers = Reseller::with('user.profile')->get();
        return view('admin.customers.create', compact('users', 'resellers'));
    }

    /**
     * ذخیره مشتری جدید
     */
    public function store(Request $request)
    {
        $customer = $this->createCustomerUseCase->execute($request->all());

        return redirect()->route('admin.customers.index')
            ->with('success', "مشتری {$customer->name} با موفقیت اضافه شد.");
    }

    /**
     * نمایش فرم ویرایش مشتری
     */
    public function edit(Customer $customer)
    {
        $customer->load(['user.profile', 'reseller.user.profile']);

        $users = User::with('profile')->orderBy('mobile')->get();
        $resellers = Reseller::with('user.profile')->get();

        return view('admin.customers.edit', compact('customer', 'users', 'resellers'));
    }

    /**
     * به‌روزرسانی مشتری
     */
    public function update(Request $request, Customer $customer)
    {
        $this->updateCustomerUseCase->execute($customer, $request->all());

        return redirect()->route('admin.customers.index')
            ->with('success', "اطلاعات مشتری {$customer->name} بروزرسانی شد.");
    }

    /**
     * حذف نرم مشتری
     */
    public function destroy(Customer $customer)
    {
        $this->deleteCustomerUseCase->execute($customer);

        return redirect()->route('admin.customers.index')
            ->with('success', 'مشتری حذف شد.');
    }

    /**
     * Bulk حذف نرم مشتریان
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'customer_ids'   => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $count = $this->bulkDeleteCustomerUseCase->execute($request->customer_ids);

        return back()->with('success', "{$count} مشتری حذف شدند.");
    }

    /**
     * Bulk بازیابی مشتریان
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'customer_ids'   => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $count = $this->bulkRestoreCustomerUseCase->execute($request->customer_ids);

        return back()->with('success', "{$count} مشتری بازیابی شدند.");
    }

    /**
     * Export مشتریان به CSV
     */
    public function exportCsv(Request $request)
    {
        $filters = $request->only(['name', 'mobile', 'email']);
        $customers = $this->customerRepository->getAllForExport($filters);

        $filename = 'customers_export_' . now()->format('Ymd_His') . '.csv';
        $path = storage_path("app/exports/{$filename}");
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'w');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, ['#', 'نام', 'شرکت', 'موبایل', 'ایمیل', 'تعداد سایت', 'نماینده', 'تاریخ ثبت']);

        foreach ($customers as $customer) {
            fputcsv($handle, [
                $customer->id,
                $customer->name,
                $customer->company_name ?? '',
                $customer->mobile,
                $customer->email ?? '',
                $customer->sites->count(),
                $customer->reseller?->company_name ?? '',
                $customer->created_at->format('Y-m-d H:i'),
            ]);
        }

        fclose($handle);

        return Storage::disk('local')->download("exports/{$filename}");
    }

    /**
     * نمایش فرم Import
     */
    public function showImportForm()
    {
        return view('admin.customers.import');
    }

    /**
     * Import مشتریان از CSV
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
                        'name'         => $row[1] ?? '',
                        'company_name' => $row[2] ?? '',
                        'mobile'       => $row[3] ?? '',
                        'email'        => $row[4] ?? '',
                    ];

                    if (empty($data['mobile'])) {
                        continue;
                    }

                    $this->createCustomerUseCase->execute($data);
                    $result['imported']++;
                } catch (\Exception $e) {
                    $result['failed'][] = $row[3] ?? 'unknown';
                    \Illuminate\Support\Facades\Log::warning("Import customer failed: {$e->getMessage()}");
                }
            }
        });

        fclose($handle);

        $message = "{$result['imported']} مشتری وارد شدند.";
        if (!empty($result['failed'])) {
            $message .= ' ' . count($result['failed']) . ' مورد ناموفق.';
        }

        return redirect()->route('admin.customers.index')->with('success', $message);
    }
}
