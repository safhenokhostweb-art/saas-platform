<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FinanceLedgerEntry extends Model
{
    // ── Types ───────────────────────────────────────────────────────────────
    const TYPE_INCOME  = 'income';
    const TYPE_EXPENSE = 'expense';

    // ── Sources ─────────────────────────────────────────────────────────────
    const SOURCE_AUTOMATIC = 'automatic';
    const SOURCE_MANUAL    = 'manual';

    // ── Income Categories ────────────────────────────────────────────────────
    const CAT_THEME_SALE      = 'theme_sale';
    const CAT_BUNDLE_SALE     = 'bundle_sale';
    const CAT_MODULE_PURCHASE = 'module_purchase';
    const CAT_HOSTING_RENEWAL = 'hosting_renewal';
    const CAT_SUBSCRIPTION    = 'subscription';
    const CAT_INSTALLMENT     = 'installment';
    const CAT_SUPPORT         = 'support';
    const CAT_OTHER_INCOME    = 'other_income';

    // ── Expense Categories ───────────────────────────────────────────────────
    const CAT_AI_COST       = 'ai_cost';
    const CAT_SERVER_COST   = 'server_cost';
    const CAT_ARVANCLOUD    = 'arvancloud';
    const CAT_DOMAIN        = 'domain';
    const CAT_ADVERTISING   = 'advertising';
    const CAT_SALARY        = 'salary';
    const CAT_SOFTWARE      = 'software';
    const CAT_OTHER_EXPENSE = 'other_expense';

    protected $table = 'finance_ledger_entries';

    protected $fillable = [
        'type', 'category', 'source', 'amount', 'description',
        'reference_type', 'reference_id',
        'site_id', 'customer_id',
        'gateway', 'gateway_ref', 'invoice_number',
        'attachment_path', 'notes',
        'entry_date', 'transaction_at', 'created_by',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'entry_date'     => 'date',
        'transaction_at' => 'datetime',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeIncome(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_INCOME);
    }

    public function scopeExpense(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_EXPENSE);
    }

    public function scopeForPeriod(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('entry_date', today());
    }

    public function scopeThisMonth(Builder $q): Builder
    {
        return $q->whereYear('entry_date', now()->year)
                 ->whereMonth('entry_date', now()->month);
    }

    public function scopeThisYear(Builder $q): Builder
    {
        return $q->whereYear('entry_date', now()->year);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    public function isAutomatic(): bool
    {
        return $this->source === self::SOURCE_AUTOMATIC;
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_INCOME ? 'درآمد' : 'هزینه';
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? $this->category;
    }

    public static function categoryLabels(): array
    {
        return [
            // Income
            self::CAT_THEME_SALE      => 'فروش قالب',
            self::CAT_BUNDLE_SALE     => 'فروش باندل قالب',
            self::CAT_MODULE_PURCHASE => 'خرید ماژول',
            self::CAT_HOSTING_RENEWAL => 'تمدید هاستینگ',
            self::CAT_SUBSCRIPTION    => 'اشتراک',
            self::CAT_INSTALLMENT     => 'اقساط',
            self::CAT_SUPPORT         => 'پشتیبانی',
            self::CAT_OTHER_INCOME    => 'سایر درآمدها',
            // Expense
            self::CAT_AI_COST       => 'هزینه هوش مصنوعی',
            self::CAT_SERVER_COST   => 'هزینه سرور',
            self::CAT_ARVANCLOUD    => 'ابر آروان',
            self::CAT_DOMAIN        => 'دامنه',
            self::CAT_ADVERTISING   => 'تبلیغات',
            self::CAT_SALARY        => 'حقوق',
            self::CAT_SOFTWARE      => 'نرم‌افزار',
            self::CAT_OTHER_EXPENSE => 'سایر هزینه‌ها',
        ];
    }

    public static function incomeCategories(): array
    {
        return [
            self::CAT_THEME_SALE, self::CAT_BUNDLE_SALE, self::CAT_MODULE_PURCHASE,
            self::CAT_HOSTING_RENEWAL, self::CAT_SUBSCRIPTION, self::CAT_INSTALLMENT,
            self::CAT_SUPPORT, self::CAT_OTHER_INCOME,
        ];
    }

    public static function expenseCategories(): array
    {
        return [
            self::CAT_AI_COST, self::CAT_SERVER_COST, self::CAT_ARVANCLOUD,
            self::CAT_DOMAIN, self::CAT_ADVERTISING, self::CAT_SALARY,
            self::CAT_SOFTWARE, self::CAT_OTHER_EXPENSE,
        ];
    }
}
