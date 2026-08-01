<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\FiltersCrmListings;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use SaaSPlatform\Modules\Crm\Models\CrmTag;

class CrmOpportunityController extends Controller
{
    use FiltersCrmListings;

    public function index(int $siteId, Request $request)
    {
        $pipelineId = $request->integer('pipeline_id') ?: CrmPipeline::where('site_id', $siteId)->where('is_default', true)->value('id');

        $opportunities = $this->applyListFilters(
            CrmOpportunity::where('site_id', $siteId)->with(['contact', 'company', 'stage']),
            $request,
            ['name', 'created_at', 'amount', 'expected_close_date'],
            extraFilter: function ($query, Request $req) use ($pipelineId) {
                if ($pipelineId) {
                    $query->where('pipeline_id', $pipelineId);
                }
                if ($req->filled('stage_id')) {
                    $query->where('stage_id', $req->integer('stage_id'));
                }
                if ($req->filled('status')) {
                    $query->where('status', $req->string('status')->toString());
                }
            },
        );

        $pipelines = CrmPipeline::where('site_id', $siteId)->with('stages')->orderBy('position')->get();
        $tags = CrmTag::where('site_id', $siteId)->orderBy('name')->get();

        return view('crm::admin.opportunities.index', compact('siteId', 'opportunities', 'pipelines', 'pipelineId', 'tags'));
    }

    /** Phase 20 — Part 3: Sales Kanban — one column per Stage, open deals only. */
    public function kanban(int $siteId, Request $request)
    {
        $pipelineId = $request->integer('pipeline_id') ?: CrmPipeline::where('site_id', $siteId)->where('is_default', true)->value('id');
        $pipeline = CrmPipeline::where('site_id', $siteId)->with('stages')->findOrFail($pipelineId);

        $opportunities = CrmOpportunity::where('site_id', $siteId)
            ->where('pipeline_id', $pipelineId)
            ->open()
            ->active()
            ->with(['contact', 'company'])
            ->get()
            ->groupBy('stage_id');

        $pipelines = CrmPipeline::where('site_id', $siteId)->orderBy('position')->get();

        return view('crm::admin.opportunities.kanban', compact('siteId', 'pipeline', 'pipelines', 'opportunities'));
    }

    public function store(Request $request, int $siteId)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_id' => 'nullable|integer|exists:crm_contacts,id',
            'company_id' => 'nullable|integer|exists:crm_companies,id',
            'pipeline_id' => 'required|integer|exists:crm_pipelines,id',
            'stage_id' => 'required|integer|exists:crm_stages,id',
            'amount' => 'nullable|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'owner_user_id' => 'nullable|integer',
        ]);
        $data['site_id'] = $siteId;

        CrmOpportunity::create($data);

        return redirect()->route('crm.admin.opportunities.index', $siteId)->with('success', 'معامله ثبت شد.');
    }

    public function update(Request $request, int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_id' => 'nullable|integer|exists:crm_contacts,id',
            'company_id' => 'nullable|integer|exists:crm_companies,id',
            'amount' => 'nullable|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'owner_user_id' => 'nullable|integer',
        ]);

        $opportunity->update($data);

        return redirect()->route('crm.admin.opportunities.index', $siteId)->with('success', 'معامله بروزرسانی شد.');
    }

    public function destroy(int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);
        $opportunity->delete();

        return redirect()->route('crm.admin.opportunities.index', $siteId)->with('success', 'معامله حذف شد.');
    }

    /** Drag-and-drop / dropdown stage change — this is what actually runs Sales Pipeline business rules (won/lost auto-handling). */
    /**
     * Phase 20 — Part 3: the same route serves both the plain admin
     * dropdown (redirect back) and the Kanban board's drag-and-drop
     * (JSON) — no separate API endpoint needed, just a response-format
     * branch on the existing route.
     */
    public function moveStage(Request $request, int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);

        $data = $request->validate(['stage_id' => 'required|integer|exists:crm_stages,id']);
        $stage = CrmStage::where('pipeline_id', $opportunity->pipeline_id)->findOrFail($data['stage_id']);

        $opportunity->moveToStage($stage);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'opportunity' => $opportunity->fresh(['stage']),
                'stage_changed_at' => $opportunity->stage_changed_at,
            ]);
        }

        return back()->with('success', 'مرحله معامله بروزرسانی شد.');
    }

    public function markWon(Request $request, int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);

        $data = $request->validate(['won_reason' => 'nullable|string|max:255']);
        $opportunity->markWon($data['won_reason'] ?? null);

        return back()->with('success', 'معامله برد ثبت شد.');
    }

    public function markLost(Request $request, int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);

        $data = $request->validate(['lost_reason' => 'nullable|string|max:255']);
        $opportunity->markLost($data['lost_reason'] ?? null);

        return back()->with('success', 'باخت معامله ثبت شد.');
    }

    public function toggleFavorite(int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);
        $opportunity->toggleFavorite();

        return back();
    }

    public function syncTags(Request $request, int $siteId, CrmOpportunity $opportunity)
    {
        abort_unless($opportunity->site_id === $siteId, 404);

        $data = $request->validate(['tags' => 'nullable|array', 'tags.*' => 'integer']);
        $opportunity->syncTags($data['tags'] ?? []);

        return back();
    }

    public function bulk(Request $request, int $siteId)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:archive,unarchive,favorite,unfavorite,delete',
        ]);

        $count = $this->applyBulkAction(CrmOpportunity::class, $siteId, $data['ids'], $data['action']);

        return back()->with('success', "{$count} مورد بروزرسانی شد.");
    }

    /** Part 5: Forecast — expected vs weighted revenue, grouped by stage, for the currently selected pipeline. */
    public function forecast(int $siteId, Request $request)
    {
        $pipelineId = $request->integer('pipeline_id') ?: CrmPipeline::where('site_id', $siteId)->where('is_default', true)->value('id');
        $pipeline = CrmPipeline::where('site_id', $siteId)->with('stages')->findOrFail($pipelineId);

        $openOpportunities = CrmOpportunity::where('site_id', $siteId)
            ->where('pipeline_id', $pipelineId)
            ->open()
            ->active()
            ->get();

        $byStage = $pipeline->stages->map(function (CrmStage $stage) use ($openOpportunities) {
            $inStage = $openOpportunities->where('stage_id', $stage->id);

            return [
                'stage' => $stage,
                'count' => $inStage->count(),
                'total_amount' => $inStage->sum('amount'),
                'weighted_amount' => $inStage->sum(fn (CrmOpportunity $o) => $o->weightedAmount()),
            ];
        });

        $totals = [
            'count' => $openOpportunities->count(),
            'total_amount' => $openOpportunities->sum('amount'),
            'weighted_amount' => $openOpportunities->sum(fn (CrmOpportunity $o) => $o->weightedAmount()),
        ];

        $pipelines = CrmPipeline::where('site_id', $siteId)->orderBy('position')->get();

        return view('crm::admin.opportunities.forecast', compact('siteId', 'pipeline', 'pipelines', 'byStage', 'totals'));
    }
}
