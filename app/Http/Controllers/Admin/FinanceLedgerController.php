<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FinanceLedgerEntry;
use App\Models\Site;
use App\Services\Finance\FinanceLedgerService;
use Illuminate\Http\Request;

class FinanceLedgerController extends Controller
{
    public function __construct(private FinanceLedgerService $ledger) {}

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $kpis          = $this->ledger->kpis();
        $topRevenue    = $this->ledger->topRevenueCategories();
        $topExpense    = $this->ledger->topExpenseCategories();
        $topSites      = $this->ledger->topSites();
        $monthlyTrend  = $this->ledger->monthlyTrend(6);

        // Recent entries (last 10)
        $recent = FinanceLedgerEntry::orderByDesc('entry_date')
            ->orderByDesc('id')
            ->with(['site:id,title', 'customer:id,name'])
            ->limit(10)
            ->get();

        return view('admin.finance.dashboard', compact(
            'kpis', 'topRevenue', 'topExpense', 'topSites', 'monthlyTrend', 'recent'
        ));
    }

    // ── Ledger Index ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $q = FinanceLedgerEntry::query()
            ->with(['site:id,title', 'customer:id,name'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        // Filters
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('category')) {
            $q->where('category', $request->category);
        }
        if ($request->filled('source')) {
            $q->where('source', $request->source);
        }
        if ($request->filled('site_id')) {
            $q->where('site_id', $request->site_id);
        }
        if ($request->filled('gateway')) {
            $q->where('gateway', $request->gateway);
        }
        if ($request->filled('date_from')) {
            $q->where('entry_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('entry_date', '<=', $request->date_to);
        }
        if ($request->filled('amount_min')) {
            $q->where('amount', '>=', (int) $request->amount_min);
        }
        if ($request->filled('amount_max')) {
            $q->where('amount', '<=', (int) $request->amount_max);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $q->where(function ($sub) use ($search) {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('gateway_ref', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $entries = $q->paginate(25)->withQueryString();

        // Totals for filtered set
        $totalIncome  = (clone $q)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $q)->where('type', 'expense')->sum('amount');

        $sites    = Site::select('id', 'title')->orderBy('title')->get();
        $labels   = FinanceLedgerEntry::categoryLabels();

        return view('admin.finance.ledger.index', compact(
            'entries', 'totalIncome', 'totalExpense', 'sites', 'labels'
        ));
    }

    // ── Manual Entry: Create ─────────────────────────────────────────────────

    public function create()
    {
        $sites     = Site::select('id', 'title')->orderBy('title')->get();
        $customers = Customer::select('id', 'name')->orderBy('name')->get();

        return view('admin.finance.ledger.create', compact('sites', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'           => 'required|in:income,expense',
            'category'       => 'required|string|max:50',
            'amount'         => 'required|integer|min:1',
            'description'    => 'nullable|string|max:1000',
            'site_id'        => 'nullable|exists:sites,id',
            'invoice_number' => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:2000',
            'entry_date'     => 'required|date',
        ]);

        // Validate category belongs to correct type
        $incomeCategories  = FinanceLedgerEntry::incomeCategories();
        $expenseCategories = FinanceLedgerEntry::expenseCategories();
        $allowedCategories = $validated['type'] === 'income' ? $incomeCategories : $expenseCategories;

        if (!in_array($validated['category'], $allowedCategories)) {
            return back()->withErrors(['category' => 'دسته‌بندی انتخابی با نوع تراکنش مطابقت ندارد.'])->withInput();
        }

        // Handle attachment
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('finance/attachments', 'public');
            $validated['attachment_path'] = $path;
        }

        $this->ledger->createManual($validated, auth()->id());

        return redirect()->route('admin.finance-ledger.index')
            ->with('success', 'تراکنش با موفقیت ثبت شد.');
    }

    // ── Edit ─────────────────────────────────────────────────────────────────

    public function edit(FinanceLedgerEntry $financeLedgerEntry)
    {
        $sites     = Site::select('id', 'title')->orderBy('title')->get();
        $customers = Customer::select('id', 'name')->orderBy('name')->get();

        return view('admin.finance.ledger.edit', [
            'entry'     => $financeLedgerEntry,
            'sites'     => $sites,
            'customers' => $customers,
        ]);
    }

    public function update(Request $request, FinanceLedgerEntry $financeLedgerEntry)
    {
        $validated = $request->validate([
            'type'           => 'required|in:income,expense',
            'category'       => 'required|string|max:50',
            'amount'         => 'required|integer|min:1',
            'description'    => 'nullable|string|max:1000',
            'site_id'        => 'nullable|exists:sites,id',
            'invoice_number' => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:2000',
            'entry_date'     => 'required|date',
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('finance/attachments', 'public');
            $validated['attachment_path'] = $path;
        }

        $financeLedgerEntry->update($validated);

        return redirect()->route('admin.finance-ledger.index')
            ->with('success', 'تراکنش با موفقیت به‌روزرسانی شد.');
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function destroy(FinanceLedgerEntry $financeLedgerEntry)
    {
        $financeLedgerEntry->delete();

        return back()->with('success', 'تراکنش حذف شد.');
    }

    // ── Export CSV ───────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $q = FinanceLedgerEntry::query()->orderByDesc('entry_date');

        if ($request->filled('type'))      $q->where('type', $request->type);
        if ($request->filled('category'))  $q->where('category', $request->category);
        if ($request->filled('date_from')) $q->where('entry_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $q->where('entry_date', '<=', $request->date_to);

        $entries = $q->with(['site:id,title'])->get();
        $labels  = FinanceLedgerEntry::categoryLabels();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="finance-ledger-' . now()->format('Ymd') . '.csv"',
        ];

        $callback = function () use ($entries, $labels) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['تاریخ', 'نوع', 'دسته‌بندی', 'منبع', 'مبلغ (ریال)', 'شرح', 'سایت', 'درگاه', 'شماره فاکتور', 'یادداشت']);

            foreach ($entries as $e) {
                fputcsv($handle, [
                    $e->entry_date?->format('Y-m-d'),
                    $e->typeLabel(),
                    $labels[$e->category] ?? $e->category,
                    $e->source === 'automatic' ? 'خودکار' : 'دستی',
                    $e->amount,
                    $e->description,
                    $e->site?->title ?? '',
                    $e->gateway ?? '',
                    $e->invoice_number ?? '',
                    $e->notes ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
