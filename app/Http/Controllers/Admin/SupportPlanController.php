<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportPlan;
use Illuminate\Http\Request;

class SupportPlanController extends Controller
{
    public function index()
    {
        $plans = SupportPlan::orderBy('duration_days')->get();
        return view('admin.support-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.support-plans.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'has_ticket' => 'nullable|boolean',
            'has_sms' => 'nullable|boolean',
        ]);
        $data['has_ticket'] = $request->boolean('has_ticket');
        $data['has_sms'] = $request->boolean('has_sms');
        SupportPlan::create($data);
        return redirect()->route('admin.support-plans.index')->with('success', 'پلن پشتیبانی ذخیره شد.');
    }

    public function edit(SupportPlan $supportPlan)
    {
        return view('admin.support-plans.edit', compact('supportPlan'));
    }

    public function update(Request $request, SupportPlan $supportPlan)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'duration_days' => 'required|integer|min:1',
            'has_ticket' => 'nullable|boolean',
            'has_sms' => 'nullable|boolean',
        ]);
        $data['has_ticket'] = $request->boolean('has_ticket');
        $data['has_sms'] = $request->boolean('has_sms');
        $supportPlan->update($data);
        return redirect()->route('admin.support-plans.index')->with('success', 'پلن پشتیبانی به‌روزرسانی شد.');
    }

    public function destroy(SupportPlan $supportPlan)
    {
        $supportPlan->delete();
        return redirect()->route('admin.support-plans.index')->with('success', 'پلن پشتیبانی حذف شد.');
    }
}
