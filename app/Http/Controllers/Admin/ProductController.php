<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Module;
use App\Models\SupportPlan;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['module', 'supportPlan'])->latest()->get();
        return view('admin.products.index', compact('products'));
    }

    public function create(Request $request)
    {
        $modules = Module::assignableToSite()->where('is_active', true)->orderBy('title')->get();
        $supportPlans = SupportPlan::all();
        $preselectedModuleId = $request->filled('module_id') ? (int) $request->module_id : null;
        return view('admin.products.create', compact('modules', 'supportPlans', 'preselectedModuleId'));
    }

    public function store(Request $request)
    {
        $rules = [
            'sku' => 'required|string|max:64|unique:products,sku',
            'name_fa' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'type' => 'required|in:license_template,module_addon,support_plan,motion_asset',
            'price_irr' => 'required|integer|min:0',
            'is_one_time' => 'boolean',
            'module_id' => 'nullable|exists:modules,id',
            'support_plan_id' => 'nullable|exists:support_plans,id',
        ];
        if ($request->type === Product::TYPE_MODULE_ADDON) {
            $rules['module_id'] = [
                'required',
                'exists:modules,id',
                function ($attribute, $value, $fail) {
                    if (! Module::paidMarketplace()->whereKey($value)->exists()) {
                        $fail('ماژول انتخاب‌شده برای فروش در فروشگاه (Marketplace) در دسترس نیست.');
                    }
                },
            ];
        }
        if ($request->type === Product::TYPE_SUPPORT_PLAN) {
            $rules['support_plan_id'] = 'required|exists:support_plans,id';
        }
        $request->validate($rules);

        $data = $request->only([
            'sku', 'name_fa', 'name_en', 'type', 'price_irr',
            'module_id', 'support_plan_id', 'description',
        ]);
        $data['is_one_time'] = $request->boolean('is_one_time');
        $data['is_active'] = $request->boolean('is_active', true);

        Product::create($data);
        return redirect()->route('admin.products.index')->with('success', 'محصول ایجاد شد.');
    }

    public function edit(Product $product)
    {
        $modules = Module::assignableToSite()->where('is_active', true)->orderBy('title')->get();
        $supportPlans = SupportPlan::all();
        return view('admin.products.edit', compact('product', 'modules', 'supportPlans'));
    }

    public function update(Request $request, Product $product)
    {
        $rules = [
            'sku' => 'required|string|max:64|unique:products,sku,' . $product->id,
            'name_fa' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'type' => 'required|in:license_template,module_addon,support_plan,motion_asset',
            'price_irr' => 'required|integer|min:0',
            'module_id' => 'nullable|exists:modules,id',
            'support_plan_id' => 'nullable|exists:support_plans,id',
        ];
        if ($request->type === Product::TYPE_MODULE_ADDON) {
            $rules['module_id'] = [
                'required',
                'exists:modules,id',
                function ($attribute, $value, $fail) {
                    if (! Module::paidMarketplace()->whereKey($value)->exists()) {
                        $fail('ماژول انتخاب‌شده برای فروش در فروشگاه (Marketplace) در دسترس نیست.');
                    }
                },
            ];
        }
        if ($request->type === Product::TYPE_SUPPORT_PLAN) {
            $rules['support_plan_id'] = 'required|exists:support_plans,id';
        }
        $request->validate($rules);

        $data = $request->only([
            'sku', 'name_fa', 'name_en', 'type', 'price_irr',
            'module_id', 'support_plan_id', 'description',
        ]);
        $data['is_one_time'] = $request->boolean('is_one_time');
        $data['is_active'] = $request->boolean('is_active');

        $product->update($data);
        return redirect()->route('admin.products.index')->with('success', 'محصول بروزرسانی شد.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'محصول حذف شد.');
    }
}
