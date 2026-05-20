<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResellerCommission;
use Illuminate\Http\Request;

class ResellerCommissionController extends Controller
{
    public function index()
    {
        $pending = ResellerCommission::with(['reseller.user', 'invoice'])
            ->where('status', ResellerCommission::STATUS_PENDING)
            ->orderBy('created_at')
            ->paginate(20);

        $totalPending = ResellerCommission::where('status', ResellerCommission::STATUS_PENDING)->sum('amount_irr');

        return view('admin.reseller-commissions.index', compact('pending', 'totalPending'));
    }

    public function markPaid(ResellerCommission $commission)
    {
        if ($commission->status !== ResellerCommission::STATUS_PENDING) {
            return back()->with('error', 'این کمیسیون قبلاً تسویه شده است.');
        }

        $commission->update([
            'status' => ResellerCommission::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return back()->with('success', 'کمیسیون به‌عنوان تسویه‌شده ثبت شد.');
    }

    public function markAllPaidForReseller(int $resellerId)
    {
        $updated = ResellerCommission::where('reseller_id', $resellerId)
            ->where('status', ResellerCommission::STATUS_PENDING)
            ->update([
                'status' => ResellerCommission::STATUS_PAID,
                'paid_at' => now(),
            ]);

        return back()->with('success', $updated . ' کمیسیون تسویه شد.');
    }
}
