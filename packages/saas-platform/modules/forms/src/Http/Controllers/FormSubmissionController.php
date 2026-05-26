<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Services\FormSubmissionService;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function __construct(
        protected FormSubmissionService $submissionService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['form_id', 'site_id', 'from', 'to', 'q']);
        $data = $this->submissionService->index($filters);

        return view('forms::admin.forms.submissions.index', $data);
    }

    public function updateColumnOrder(Request $request)
    {
        $this->submissionService->updateColumnOrder($request->input('order', []));
        return response()->json(['ok' => true]);
    }

    public function export(Request $request)
    {
        $filters = $request->only(['form_id', 'site_id', 'from', 'to', 'q']);
        return $this->submissionService->export($filters);
    }
}
