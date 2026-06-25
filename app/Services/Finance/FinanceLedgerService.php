<?php

namespace App\Services\Finance;

use App\Models\FinanceLedgerEntry;
use App\Models\Invoice;
use App\Models\SiteLifecycleInvoice;
use App\Models\ThemeBundleOrder;
use App\Models\ThemePurchaseOrder;
use Illuminate\Support\Facades\Log;

class FinanceLedgerService
{
    // ── Auto-capture: Platform Models ────────────────────────────────────────

    public function fromThemePurchase(ThemePurchaseOrder $order): ?FinanceLedgerEntry
    {
        return $this->safeCreate([
            'type'           => FinanceLedgerEntry::TYPE_INCOME,
            'category'       => FinanceLedgerEntry::CAT_THEME_SALE,
            'source'         => FinanceLedgerEntry::SOURCE_AUTOMATIC,
            'amount'         => $order->amount_rial,
            'description'    => 'فروش قالب — سفارش #' . $order->id,
            'reference_type' => 'theme_purchase_orders',
            'reference_id'   => $order->id,
            'site_id'        => $order->site_id,
            'gateway'        => $order->gateway,
            'gateway_ref'    => $order->gateway_ref,
            'entry_date'     => $order->paid_at?->toDateString() ?? today()->toDateString(),
            'transaction_at' => $order->paid_at,
        ]);
    }

    public function fromBundlePurchase(ThemeBundleOrder $order): ?FinanceLedgerEntry
    {
        return $this->safeCreate([
            'type'           => FinanceLedgerEntry::TYPE_INCOME,
            'category'       => FinanceLedgerEntry::CAT_BUNDLE_SALE,
            'source'         => FinanceLedgerEntry::SOURCE_AUTOMATIC,
            'amount'         => $order->amount_rial,
            'description'    => 'فروش باندل قالب — سفارش #' . $order->id,
            'reference_type' => 'theme_bundle_orders',
            'reference_id'   => $order->id,
            'site_id'        => $order->site_id,
            'gateway'        => $order->gateway,
            'entry_date'     => today()->toDateString(),
            'transaction_at' => now(),
        ]);
    }

    public function fromInvoice(Invoice $invoice): ?FinanceLedgerEntry
    {
        if ($invoice->amount_irr <= 0) {
            return null;
        }

        return $this->safeCreate([
            'type'           => FinanceLedgerEntry::TYPE_INCOME,
            'category'       => FinanceLedgerEntry::CAT_MODULE_PURCHASE,
            'source'         => FinanceLedgerEntry::SOURCE_AUTOMATIC,
            'amount'         => $invoice->amount_irr,
            'description'    => 'خرید ماژول/محصول — فاکتور #' . $invoice->id,
            'reference_type' => 'invoices',
            'reference_id'   => $invoice->id,
            'customer_id'    => $invoice->customer_id,
            'gateway'        => $invoice->gateway,
            'gateway_ref'    => $invoice->gateway_ref,
            'entry_date'     => $invoice->paid_at?->toDateString() ?? today()->toDateString(),
            'transaction_at' => $invoice->paid_at,
        ]);
    }

    public function fromLifecycleInvoice(SiteLifecycleInvoice $invoice): ?FinanceLedgerEntry
    {
        if ($invoice->amount <= 0) {
            return null;
        }

        return $this->safeCreate([
            'type'           => FinanceLedgerEntry::TYPE_INCOME,
            'category'       => FinanceLedgerEntry::CAT_HOSTING_RENEWAL,
            'source'         => FinanceLedgerEntry::SOURCE_AUTOMATIC,
            'amount'         => $invoice->amount,
            'description'    => $invoice->typeLabel() . ' — ' . $invoice->invoice_number,
            'reference_type' => 'site_lifecycle_invoices',
            'reference_id'   => $invoice->id,
            'site_id'        => $invoice->site_id,
            'invoice_number' => $invoice->invoice_number,
            'gateway_ref'    => $invoice->payment_reference,
            'entry_date'     => $invoice->paid_at?->toDateString() ?? today()->toDateString(),
            'transaction_at' => $invoice->paid_at,
        ]);
    }

    // ── Auto-capture: Customer-site API push ─────────────────────────────────

    public function fromCustomerSitePush(array $data): ?FinanceLedgerEntry
    {
        $category = match ($data['event_type'] ?? '') {
            'subscription_payment' => FinanceLedgerEntry::CAT_SUBSCRIPTION,
            'installment_payment'  => FinanceLedgerEntry::CAT_INSTALLMENT,
            'order_payment'        => FinanceLedgerEntry::CAT_OTHER_INCOME,
            default                => FinanceLedgerEntry::CAT_OTHER_INCOME,
        };

        return $this->safeCreate([
            'type'           => FinanceLedgerEntry::TYPE_INCOME,
            'category'       => $category,
            'source'         => FinanceLedgerEntry::SOURCE_AUTOMATIC,
            'amount'         => (int) ($data['amount'] ?? 0),
            'description'    => $data['description'] ?? 'پرداخت از سایت مشتری',
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id'   => isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            'site_id'        => isset($data['site_id']) ? (int) $data['site_id'] : null,
            'gateway'        => $data['gateway'] ?? null,
            'gateway_ref'    => $data['gateway_ref'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'entry_date'     => $data['entry_date'] ?? today()->toDateString(),
            'transaction_at' => isset($data['transaction_at']) ? now()->parse($data['transaction_at']) : now(),
        ]);
    }

    // ── Manual Entry ─────────────────────────────────────────────────────────

    public function createManual(array $validated, int $adminId): FinanceLedgerEntry
    {
        return FinanceLedgerEntry::create([
            'type'           => $validated['type'],
            'category'       => $validated['category'],
            'source'         => FinanceLedgerEntry::SOURCE_MANUAL,
            'amount'         => $validated['amount'],
            'description'    => $validated['description'] ?? null,
            'site_id'        => $validated['site_id'] ?? null,
            'invoice_number' => $validated['invoice_number'] ?? null,
            'attachment_path'=> $validated['attachment_path'] ?? null,
            'notes'          => $validated['notes'] ?? null,
            'entry_date'     => $validated['entry_date'],
            'created_by'     => $adminId,
        ]);
    }

    // ── KPI Aggregation ──────────────────────────────────────────────────────

    public function kpis(): array
    {
        $today     = today()->toDateString();
        $monthFrom = now()->startOfMonth()->toDateString();
        $monthTo   = now()->endOfMonth()->toDateString();
        $yearFrom  = now()->startOfYear()->toDateString();
        $yearTo    = now()->endOfYear()->toDateString();

        return [
            'today_income'   => $this->sumIncome($today, $today),
            'month_income'   => $this->sumIncome($monthFrom, $monthTo),
            'year_income'    => $this->sumIncome($yearFrom, $yearTo),
            'today_expense'  => $this->sumExpense($today, $today),
            'month_expense'  => $this->sumExpense($monthFrom, $monthTo),
            'year_expense'   => $this->sumExpense($yearFrom, $yearTo),
            'month_profit'   => $this->sumIncome($monthFrom, $monthTo) - $this->sumExpense($monthFrom, $monthTo),
            'year_profit'    => $this->sumIncome($yearFrom, $yearTo) - $this->sumExpense($yearFrom, $yearTo),
        ];
    }

    public function topRevenueCategories(int $limit = 5): array
    {
        return FinanceLedgerEntry::income()
            ->thisMonth()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'category' => $r->category,
                'label'    => FinanceLedgerEntry::categoryLabels()[$r->category] ?? $r->category,
                'total'    => (int) $r->total,
            ])
            ->toArray();
    }

    public function topExpenseCategories(int $limit = 5): array
    {
        return FinanceLedgerEntry::expense()
            ->thisMonth()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'category' => $r->category,
                'label'    => FinanceLedgerEntry::categoryLabels()[$r->category] ?? $r->category,
                'total'    => (int) $r->total,
            ])
            ->toArray();
    }

    public function topSites(int $limit = 5): array
    {
        return FinanceLedgerEntry::income()
            ->thisMonth()
            ->whereNotNull('site_id')
            ->selectRaw('site_id, SUM(amount) as total')
            ->groupBy('site_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('site:id,title,domain')
            ->get()
            ->map(fn($r) => [
                'site_id' => $r->site_id,
                'site'    => $r->site?->title ?? 'سایت #' . $r->site_id,
                'total'   => (int) $r->total,
            ])
            ->toArray();
    }

    public function monthlyTrend(int $months = 6): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $from  = $date->copy()->startOfMonth()->toDateString();
            $to    = $date->copy()->endOfMonth()->toDateString();
            $result[] = [
                'label'   => $date->format('Y/m'),
                'income'  => $this->sumIncome($from, $to),
                'expense' => $this->sumExpense($from, $to),
            ];
        }
        return $result;
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private function sumIncome(string $from, string $to): int
    {
        return (int) FinanceLedgerEntry::income()
            ->forPeriod($from, $to)
            ->sum('amount');
    }

    private function sumExpense(string $from, string $to): int
    {
        return (int) FinanceLedgerEntry::expense()
            ->forPeriod($from, $to)
            ->sum('amount');
    }

    private function safeCreate(array $data): ?FinanceLedgerEntry
    {
        if (($data['amount'] ?? 0) <= 0) {
            return null;
        }

        try {
            return FinanceLedgerEntry::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint — already recorded (idempotent)
            if ($e->getCode() === '23000') {
                return null;
            }
            Log::error('FinanceLedgerService: failed to create entry', [
                'data'  => $data,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
