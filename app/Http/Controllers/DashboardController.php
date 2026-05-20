<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Site;
use App\Models\Form;

class DashboardController extends Controller
{
    public function index()
    {
        $customers = Customer::count();
        $sites     = Site::count();
        $forms     = Form::count();

        return view('admin.dashboard', compact(
            'customers',
            'sites',
            'forms'
        ));
    }
}
