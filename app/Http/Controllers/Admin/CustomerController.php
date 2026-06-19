<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\Jalali;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Services\ActivityLogger;
use App\Services\Infrastructure\StorageManager;
use App\UseCases\Customer\BulkDeleteCustomerUseCase;
use App\UseCases\Customer\BulkRestoreCustomerUseCase;
use App\UseCases\Customer\CreateCustomerUseCase;
use App\UseCases\Customer\DeleteCustomerUseCase;
use App\UseCases\Customer\SearchCustomersUseCase;
use App\UseCases\Customer\UpdateCustomerUseCase;
use Illuminate\Http\Request;
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
        private StorageManager $storage,
    ) {}

    /**
     * نمایش لیست مشتریان با جستجوی پیشرفته
     */
    public function index(Request $request)
    {
        $filters = $request->only(['name', 'mobile', 'reseller_id', 'has_site', 'created_from', 'created_to', 'q']);
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
        $filters   = $request->only(['name', 'mobile']);
        $customers = $this->customerRepository->getAllForExport($filters);

        $filename = 'customers_export_' . now()->format('Ymd_His') . '.csv';

        $rows = collect($customers)->map(fn ($c) => [
            $c->id,
            $c->name,
            $c->company_name ?? '',
            $c->mobile,
            $c->sites->count(),
            $c->reseller?->company_name ?? '',
            Jalali::formatFa($c->created_at, 'Y/m/d H:i'),
        ]);

        $this->storage->writeCsv('', $filename, ['#', 'نام', 'شرکت', 'موبایل', 'تعداد سایت', 'نماینده', 'تاریخ ثبت'], $rows, 'exports');

        return $this->storage->download($filename, 'exports', $filename);
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

        $diskPath = $request->file('file')->store('imports');

        if (!$this->storage->exists($diskPath, 'local')) {
            return back()->with('error', 'فایل یافت نشد.');
        }

        $result = ['imported' => 0, 'failed' => []];
        $handle = $this->storage->readStream($diskPath, 'local');
        $header = fgetcsv($handle);

        \Illuminate\Support\Facades\DB::transaction(function () use ($handle, &$result) {
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = [
                        'name'         => $row[1] ?? '',
                        'company_name' => $row[2] ?? '',
                        'mobile'       => $row[3] ?? '',
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

        if (is_resource($handle)) {
            fclose($handle);
        }

        $message = "{$result['imported']} مشتری وارد شدند.";
        if (!empty($result['failed'])) {
            $message .= ' ' . count($result['failed']) . ' مورد ناموفق.';
        }

        return redirect()->route('admin.customers.index')->with('success', $message);
    }
}
