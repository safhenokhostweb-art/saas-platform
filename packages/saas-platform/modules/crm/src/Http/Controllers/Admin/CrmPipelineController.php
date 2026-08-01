<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;

class CrmPipelineController extends Controller
{
    public function index(int $siteId)
    {
        $pipelines = CrmPipeline::where('site_id', $siteId)->with('stages')->orderBy('position')->get();

        return view('crm::admin.pipelines.index', compact('siteId', 'pipelines'));
    }

    public function store(Request $request, int $siteId)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'is_default' => 'nullable|boolean',
        ]);
        $data['site_id'] = $siteId;
        $data['position'] = CrmPipeline::where('site_id', $siteId)->max('position') + 1;

        if (!empty($data['is_default'])) {
            CrmPipeline::where('site_id', $siteId)->update(['is_default' => false]);
        }

        CrmPipeline::create($data);

        return redirect()->route('crm.admin.pipelines.index', $siteId)->with('success', 'خط لوله ثبت شد.');
    }

    public function destroy(int $siteId, CrmPipeline $pipeline)
    {
        abort_unless($pipeline->site_id === $siteId, 404);
        abort_if($pipeline->opportunities()->exists(), 422, 'این خط لوله دارای معامله است و قابل حذف نیست.');

        $pipeline->delete();

        return redirect()->route('crm.admin.pipelines.index', $siteId)->with('success', 'خط لوله حذف شد.');
    }

    public function storeStage(Request $request, int $siteId, CrmPipeline $pipeline)
    {
        abort_unless($pipeline->site_id === $siteId, 404);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'probability' => 'required|integer|min:0|max:100',
            'type' => 'required|in:open,won,lost',
        ]);
        $data['pipeline_id'] = $pipeline->id;
        $data['position'] = $pipeline->stages()->max('position') + 1;

        CrmStage::create($data);

        return redirect()->route('crm.admin.pipelines.index', $siteId)->with('success', 'مرحله ثبت شد.');
    }

    public function updateStage(Request $request, int $siteId, CrmPipeline $pipeline, CrmStage $stage)
    {
        abort_unless($pipeline->site_id === $siteId && $stage->pipeline_id === $pipeline->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'probability' => 'required|integer|min:0|max:100',
            'type' => 'required|in:open,won,lost',
            'position' => 'nullable|integer|min:0',
        ]);

        $stage->update($data);

        return redirect()->route('crm.admin.pipelines.index', $siteId)->with('success', 'مرحله بروزرسانی شد.');
    }

    public function destroyStage(int $siteId, CrmPipeline $pipeline, CrmStage $stage)
    {
        abort_unless($pipeline->site_id === $siteId && $stage->pipeline_id === $pipeline->id, 404);
        abort_if($stage->opportunities()->exists(), 422, 'این مرحله دارای معامله است و قابل حذف نیست.');

        $stage->delete();

        return redirect()->route('crm.admin.pipelines.index', $siteId)->with('success', 'مرحله حذف شد.');
    }
}
