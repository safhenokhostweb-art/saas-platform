<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\ResellerCommission;
use Illuminate\Http\Request;

class PayoutRequestController extends Controller
{
    public function index()
    {
        $requests = PayoutRequest::with('reseller.user')
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->latest()
            ->paginate(20);

        return view('admin.payout-requests.index', compact('requests'));
    }

    public function markPaid(PayoutRequest $payoutRequest)
    {
        if ($payoutRequest->status === PayoutRequest::STATUS_PAID) {
            return back()->with('error', 'این درخواست قبلاً پرداخت شده است.');
        }

        \DB::transaction(function () use ($payoutRequest) {
            ResellerCommission::where('reseller_id', $payoutRequest->reseller_id)
                ->where('status', 'pending')
                ->update(['status' => 'paid', 'paid_at' => now()]);

            $payoutRequest->update([
                'status' => PayoutRequest::STATUS_PAID,
                'processed_at' => now(),
            ]);
        });

        return back()->with('success', 'تسویه انجام شد.');
    }

    public function reject(Request $request, PayoutRequest $payoutRequest)
    {
        $payoutRequest->update([
            'status' => PayoutRequest::STATUS_REJECTED,
            'admin_note' => $request->input('admin_note'),
            'processed_at' => now(),
        ]);

        return back()->with('success', 'درخواست رد شد.');
    }
}
