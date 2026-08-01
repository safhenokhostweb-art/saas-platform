<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\Modules\Crm\Models\Concerns\Archivable;
use SaaSPlatform\Modules\Crm\Models\Concerns\Favoritable;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasActivitiesAndTasks;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;
use SaaSPlatform\Modules\Crm\Models\Concerns\Taggable;

/**
 * Phase 19 — Part 5: the Deal entity. `probability` defaults to the
 * Stage's own probability (see effectiveProbability()) but can be
 * overridden per-deal. Moving to a stage of type=won/lost auto-populates
 * status/closed_at (see markWon()/markLost()) — this is the only place
 * Sales Pipeline business rules live, not scattered across controllers.
 */
class CrmOpportunity extends Model
{
    use Archivable;
    use Favoritable;
    use HasActivitiesAndTasks;
    use HasSiteTenantScope;
    use SoftDeletes;
    use Taggable;

    public const STATUS_OPEN = 'open';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';

    protected $table = 'crm_opportunities';

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'amount' => 0,
    ];

    protected $fillable = [
        'site_id',
        'name',
        'contact_id',
        'company_id',
        'pipeline_id',
        'stage_id',
        'amount',
        'probability',
        'expected_close_date',
        'status',
        'lost_reason',
        'won_reason',
        'closed_at',
        'owner_user_id',
        'is_favorite',
        'archived_at',
        'stale_notified_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'closed_at' => 'datetime',
        'stage_changed_at' => 'datetime',
        'stale_notified_at' => 'datetime',
        'is_favorite' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    /** The deal's own override if set, otherwise the current stage's default. */
    public function effectiveProbability(): int
    {
        return $this->probability ?? $this->stage?->probability ?? 0;
    }

    /** Expected Revenue weighted by probability — the basis for Forecast. */
    public function weightedAmount(): int
    {
        return (int) round($this->amount * ($this->effectiveProbability() / 100));
    }

    /**
     * Phase 20 — Part 3: the single entry point for Kanban drag-and-drop
     * (and the plain admin dropdown) — records when the move happened
     * and logs a system Activity, on top of the won/lost auto-handling
     * that already existed (Phase 19 — Part 5).
     */
    public function moveToStage(CrmStage $stage): void
    {
        $fromStage = $this->stage;

        $this->stage_id = $stage->id;
        $this->stage_changed_at = now();

        if ($stage->type === CrmStage::TYPE_WON) {
            $this->markWon();
        } elseif ($stage->type === CrmStage::TYPE_LOST) {
            $this->markLost();
        } else {
            $this->save();
        }

        if ($fromStage && $fromStage->id !== $stage->id) {
            $this->activities()->create([
                'site_id' => $this->site_id,
                'type' => CrmActivity::TYPE_SYSTEM,
                'subject' => 'تغییر مرحله معامله',
                'body' => "مرحله از «{$fromStage->name}» به «{$stage->name}» تغییر کرد.",
                'occurred_at' => $this->stage_changed_at,
            ]);
        }
    }

    public function markWon(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_WON,
            'won_reason' => $reason ?? $this->won_reason,
            'closed_at' => now(),
        ]);
    }

    public function markLost(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_LOST,
            'lost_reason' => $reason ?? $this->lost_reason,
            'closed_at' => now(),
        ]);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return empty($term) ? $query : $query->where('name', 'like', "%{$term}%");
    }
}
