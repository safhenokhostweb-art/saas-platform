<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Rules\IranianMobile;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::latest()->paginate(10);
        return view('admin.customers.index', compact('customers'));
    }

    public function create()
    {
        return view('admin.customers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email',
            'mobile'       => ['required', 'unique:customers,mobile', new IranianMobile],
            'company_name' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ]);

        $customer = \App\Models\Customer::create($data);
        ActivityLogger::log('customer.created', 'ایجاد مشتری: ' . ($customer->company_name ?: $customer->name), $customer);

        return redirect()->route('admin.customers.index')
            ->with('success', 'مشتری با موفقیت اضافه شد');
    }



    public function edit(Customer $customer)
    {
        $hasUserColumn = Schema::hasColumn('customers', 'user_id');
        $users = $hasUserColumn ? User::with('profile')->orderBy('mobile')->get() : collect();
        $hasResellerColumn = Schema::hasColumn('customers', 'reseller_id');
        $resellers = $hasResellerColumn ? Reseller::with('user')->get() : collect();
        return view('admin.customers.edit', compact('customer', 'users', 'hasUserColumn', 'resellers', 'hasResellerColumn'));
    }

    public function update(Request $request, Customer $customer)
    {
        $rules = [
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email',
            'mobile'       => ['required', 'unique:customers,mobile,' . $customer->id, new IranianMobile],
            'company_name' => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ];
        if (Schema::hasColumn('customers', 'user_id')) {
            $rules['user_id'] = 'nullable|exists:users,id';
        }
        if (Schema::hasColumn('customers', 'reseller_id')) {
            $rules['reseller_id'] = 'nullable|exists:resellers,id';
        }
        $data = $request->validate($rules);

        if (Schema::hasColumn('customers', 'user_id')) {
            $data['user_id'] = $request->input('user_id') ?: null;
        }
        if (Schema::hasColumn('customers', 'reseller_id')) {
            $data['reseller_id'] = $request->input('reseller_id') ?: null;
        }
        $customer->update($data);
        ActivityLogger::log('customer.updated', 'ویرایش مشتری: ' . ($customer->company_name ?: $customer->name), $customer);

        return redirect()->route('admin.customers.index')
            ->with('success', 'اطلاعات مشتری بروزرسانی شد');
    }

    public function destroy(Customer $customer)
    {
        $name = $customer->company_name ?: $customer->name;
        $id = $customer->id;
        $customer->delete();
        ActivityLogger::log('customer.deleted', 'حذف مشتری: ' . $name, null, ['deleted_id' => $id]);
        return redirect()->route('admin.customers.index')
            ->with('success', 'مشتری حذف شد');
    }
}
