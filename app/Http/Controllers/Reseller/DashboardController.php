<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $reseller = $request->attributes->get('current_reseller');
        $reseller->load(['customers.sites', 'commissions']);

        $customerIds = $reseller->customers->pluck('id');
        $invoices = Invoice::whereIn('customer_id', $customerIds)
            ->where('status', 'paid')
            ->with('customer')
            ->latest()
            ->take(20)
            ->get();

        $totalSales = Invoice::whereIn('customer_id', $customerIds)
            ->where('status', 'paid')
            ->sum('amount_irr');

        $commissionsPending = $reseller->commissions->where('status', 'pending');
        $commissionsPaid = $reseller->commissions->where('status', 'paid');
        $totalPending = $commissionsPending->sum('amount_irr');
        $totalPaid = $commissionsPaid->sum('amount_irr');

        return view('reseller.dashboard', compact(
            'reseller',
            'invoices',
            'totalSales',
            'commissionsPending',
            'commissionsPaid',
            'totalPending',
            'totalPaid'
        ));
    }
}
