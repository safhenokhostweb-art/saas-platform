<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Rules\IranianMobile;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * نمایش لیست مشتریان با جستجوی پیشرفته
     */
    public function index(Request $request)
    {
        $filters = $request->only(['name', 'mobile', 'email', 'reseller_id', 'has_site', 'created_from', 'created_to', 'q']);
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');

        $customers = $this->customerService->search($filters, $sortBy, $sortDir);
        $resellers = Reseller::with('user.profile')->orderBy('company_name')->get();
        $stats = $this->customerService->getStats();

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
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email',
            'mobile'       => ['required', 'unique:customers,mobile', new IranianMobile],
            'company_name' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
            'user_id'      => 'nullable|exists:users,id',
            'reseller_id'  => 'nullable|exists:resellers,id',
        ], [
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $customer = $this->customerService->create($validator->validated());

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
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email',
            'mobile'       => ['required', 'unique:customers,mobile,' . $customer->id, new IranianMobile],
            'company_name' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
            'user_id'      => 'nullable|exists:users,id',
            'reseller_id'  => 'nullable|exists:resellers,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->customerService->update($customer, $validator->validated());

        return redirect()->route('admin.customers.index')
            ->with('success', "اطلاعات مشتری {$customer->name} بروزرسانی شد.");
    }

    /**
     * حذف نرم مشتری
     */
    public function destroy(Customer $customer)
    {
        $this->customerService->softDelete($customer);

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

        $count = $this->customerService->bulkSoftDelete($request->customer_ids);

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

        $count = $this->customerService->bulkRestore($request->customer_ids);

        return back()->with('success', "{$count} مشتری بازیابی شدند.");
    }

    /**
     * Export مشتریان به CSV
     */
    public function exportCsv(Request $request)
    {
        $filters = $request->only(['name', 'mobile', 'email']);
        $filename = $this->customerService->exportCsv($filters);

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

        $result = $this->customerService->importCsv($fullPath);

        $message = "{$result['imported']} مشتری وارد شدند.";
        if (!empty($result['failed'])) {
            $message .= ' ' . count($result['failed']) . ' مورد ناموفق.';
        }

        return redirect()->route('admin.customers.index')->with('success', $message);
    }
}
