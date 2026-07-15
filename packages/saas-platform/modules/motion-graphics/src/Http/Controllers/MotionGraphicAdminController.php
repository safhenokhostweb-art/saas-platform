<?php

namespace SaaSPlatform\Modules\MotionGraphics\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionAiEditingService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionDeploymentService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;

final class MotionGraphicAdminController extends Controller
{
    public function __construct(
        private readonly MotionLibraryService $library,
        private readonly PlatformAdminResolverContract $admins,
        private readonly MotionPermissionGatewayContract $permissions,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request, 'motion.view');
        $motions = MotionGraphic::query()
            ->with(['group', 'category', 'tags'])
            ->withCount('deployments')
            ->search($request->string('search')->toString())
            ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->integer('group_id')))
            ->when($request->filled('ownership_type'), fn ($query) => $query->where('ownership_type', $request->string('ownership_type')))
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $groups = MotionGroup::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('motion-graphics::admin.index', compact('motions', 'groups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'motion.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:160', 'unique:motion_graphics,slug'],
            'description' => ['nullable', 'string'],
            'ownership_type' => ['required', Rule::in(['global', 'customer_specific'])],
            'owner_site_identifier' => ['nullable', 'required_if:ownership_type,customer_specific', 'string', 'max:191'],
            'group_id' => ['required', 'integer', 'exists:motion_groups,id'],
            'category_id' => ['nullable', 'integer', 'exists:motion_categories,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'exists:motion_tags,id'],
            'engine' => ['required', 'string', 'max:32'],
            'asset_type' => ['required', 'string', 'max:32'],
            'editable_schema_json' => ['required', 'array'],
            'runtime_configuration' => ['nullable', 'array'],
            'dimensions' => ['nullable', 'array'],
            'accessibility' => ['nullable', 'array'],
            'pipeline_input' => ['required', 'array'],
        ]);
        $this->library->createFromPipeline($data, $data['pipeline_input'], $request->user());

        return back()->with('success', 'Motion draft created.');
    }

    public function createVersion(Request $request, MotionGraphic $motion): RedirectResponse
    {
        $data = $request->validate([
            'pipeline_input' => ['required', 'array'],
            'description' => ['required', 'string', 'max:1000'],
        ]);
        $this->library->createVersionFromPipeline(
            $motion,
            $data['pipeline_input'],
            $data['description'],
            $request->user(),
        );

        return back()->with('success', 'Immutable draft version created.');
    }

    public function aiEdit(Request $request, MotionGraphic $motion, MotionAiEditingService $editor): RedirectResponse
    {
        $data = $request->validate([
            'instruction' => ['required', 'string', 'max:4000'],
            'pipeline_input' => ['required', 'array'],
        ]);
        $editor->edit($motion, $data['instruction'], $data['pipeline_input'], $request->user());

        return back()->with('success', 'AI proposal saved as a draft.');
    }

    public function approve(Request $request, MotionGraphic $motion, int $version): RedirectResponse
    {
        $this->library->approve($motion, $version, $request->user());
        return back()->with('success', 'Version approved.');
    }

    public function publish(Request $request, MotionGraphic $motion, int $version): RedirectResponse
    {
        $this->library->publish($motion, $version, $request->user());
        return back()->with('success', 'Version published.');
    }

    public function rollback(Request $request, MotionGraphic $motion, int $version): RedirectResponse
    {
        $this->library->rollback(
            $motion,
            $version,
            (string) $request->input('description', ''),
            $request->user(),
        );
        return back()->with('success', 'Historical source restored as a new draft.');
    }

    public function deploy(
        Request $request,
        MotionGraphic $motion,
        MotionDeploymentService $deployments,
    ): RedirectResponse {
        $data = $request->validate([
            'external_site_identifier' => ['required', 'string', 'max:191'],
            'page_identifier' => ['required', 'alpha_dash', 'max:120'],
            'section_identifier' => ['nullable', 'alpha_dash', 'max:120'],
            'placement_identifier' => ['required', 'alpha_dash', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            'follow_latest_published' => ['nullable', 'boolean'],
            'selected_version' => ['nullable', 'integer', 'min:1'],
        ]);
        $deployments->assign(
            $motion,
            $data['external_site_identifier'],
            $data['page_identifier'],
            $data['placement_identifier'],
            $request->user(),
            $data['section_identifier'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            (bool) ($data['is_enabled'] ?? true),
            (bool) ($data['follow_latest_published'] ?? true),
            isset($data['selected_version']) ? (int) $data['selected_version'] : null,
        );

        return back()->with('success', 'Deployment preparation saved.');
    }

    private function authorizeAdmin(Request $request, string $ability): void
    {
        abort_unless(
            $this->admins->isPlatformAdmin($request->user())
            && $this->permissions->allows($request->user(), $ability),
            403,
        );
    }
}
