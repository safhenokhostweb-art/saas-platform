<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;

class PayoutRequestController extends Controller
{
    public function store(Request $request)
    {
        $reseller = $request->attributes->get('current_reseller');
        $totalPending = $reseller->commissions()->where('status', 'pending')->sum('amount_irr');

        if ($totalPending <= 0) {
            return back()->with('error', 'کمیسیون در انتظاری برای تسویه وجود ندارد.');
        }

        $existing = PayoutRequest::where('reseller_id', $reseller->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            return back()->with('error', 'یک درخواست تسویه در انتظار دارید.');
        }

        PayoutRequest::create([
            'reseller_id' => $reseller->id,
            'amount_irr' => $totalPending,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'درخواست تسویه ثبت شد. مبلغ: ' . number_format($totalPending) . ' ریال');
    }
}
