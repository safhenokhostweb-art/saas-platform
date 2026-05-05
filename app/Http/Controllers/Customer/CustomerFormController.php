<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Helpers\CustomerMenu;
use App\Models\Form;
use Illuminate\Http\Request;

class CustomerFormController extends Controller
{
    public function index(Request $request)
    {
        if (!CustomerMenu::isVisible('forms')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();
        $customer = $user->customer;
        $siteIds = $customer ? $customer->sites()->pluck('id') : collect();

        $forms = collect();
        if ($siteIds->isNotEmpty()) {
            $forms = Form::whereHas('sites', function ($q) use ($siteIds) {
                $q->whereIn('sites.id', $siteIds);
            })->with(['sites' => function ($q) use ($siteIds) {
                $q->whereIn('sites.id', $siteIds)->withPivot('is_active');
            }])->orderBy('title')->get();
        }

        return view('customer.forms.index', compact('forms', 'customer'));
    }
}
