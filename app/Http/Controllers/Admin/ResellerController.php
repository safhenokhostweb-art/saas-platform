<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function index()
    {
        $resellers = Reseller::with(['user', 'customers', 'commissions'])->latest()->paginate(15);
        return view('admin.resellers.index', compact('resellers'));
    }

    public function create()
    {
        $users = User::with('profile')->whereDoesntHave('reseller')->where('is_active', true)->orderBy('mobile')->get();
        $reseller = new Reseller();
        return view('admin.resellers.create', compact('users', 'reseller'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id|unique:resellers,user_id',
            'company_name' => 'nullable|string|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'wholesale_discount_percent' => 'nullable|numeric|min:0|max:100',
            'bank_info' => 'nullable|string|max:500',
            'is_white_label' => 'boolean',
            'brand_name' => 'nullable|string|max:255',
            'logo_path' => 'nullable|string|max:255',
            'custom_domain' => 'nullable|string|max:255',
            'subdomain' => 'nullable|string|max:64',
        ]);
        $data['wholesale_discount_percent'] = (float) ($data['wholesale_discount_percent'] ?? 0);
        $data['is_white_label'] = $request->boolean('is_white_label');

        $reseller = Reseller::create($data);
        ActivityLogger::log('reseller.created', 'ایجاد نماینده: ' . ($reseller->company_name ?: 'Reseller #' . $reseller->id), $reseller);

        return redirect()->route('admin.resellers.index')
            ->with('success', 'نماینده با موفقیت اضافه شد.');
    }

    public function edit(Reseller $reseller)
    {
        $users = User::with('profile')->where(function ($q) use ($reseller) {
            $q->whereDoesntHave('reseller')->orWhere('id', $reseller->user_id);
        })->where('is_active', true)->orderBy('mobile')->get();
        return view('admin.resellers.edit', compact('reseller', 'users'));
    }

    public function update(Request $request, Reseller $reseller)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id|unique:resellers,user_id,' . $reseller->id,
            'company_name' => 'nullable|string|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'wholesale_discount_percent' => 'nullable|numeric|min:0|max:100',
            'bank_info' => 'nullable|string|max:500',
            'is_white_label' => 'boolean',
            'brand_name' => 'nullable|string|max:255',
            'logo_path' => 'nullable|string|max:255',
            'custom_domain' => 'nullable|string|max:255',
            'subdomain' => 'nullable|string|max:64',
        ]);
        $data['wholesale_discount_percent'] = (float) ($data['wholesale_discount_percent'] ?? 0);
        $data['is_white_label'] = $request->boolean('is_white_label');

        $reseller->update($data);
        ActivityLogger::log('reseller.updated', 'ویرایش نماینده: ' . ($reseller->company_name ?: 'Reseller #' . $reseller->id), $reseller);

        return redirect()->route('admin.resellers.index')
            ->with('success', 'اطلاعات نماینده بروزرسانی شد.');
    }

    public function destroy(Reseller $reseller)
    {
        $name = $reseller->company_name ?: 'Reseller #' . $reseller->id;
        $id = $reseller->id;
        $reseller->delete();
        ActivityLogger::log('reseller.deleted', 'حذف نماینده: ' . $name, null, ['deleted_id' => $id]);
        return redirect()->route('admin.resellers.index')
            ->with('success', 'نماینده حذف شد.');
    }
}
