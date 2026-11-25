<?php

namespace App\Http\Controllers\Admin\Infrastructure\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait RespondsToInfraTab
{
    protected function isInfraTabRequest(?Request $request = null): bool
    {
        $req = $request ?? request();

        return $req->header('X-Infra-Tab') === '1' || $req->boolean('_infra_tab');
    }

    protected function infraTabViewResponse(View $view): JsonResponse
    {
        $sections = $view->renderSections();

        return response()->json([
            'ok' => true,
            'html' => $sections['content'] ?? $view->render(),
            'head' => $sections['head'] ?? '',
            'scripts' => $sections['scripts'] ?? '',
        ]);
    }

    protected function infraTabRedirectResponse(RedirectResponse $redirect): JsonResponse
    {
        $session = $redirect->getSession();
        $success = $session?->get('success');
        $error = $session?->get('error');

        // Clear one-time flash from session so a subsequent full reload does not replay it
        if ($session) {
            $session->forget(['success', 'error']);
        }

        return response()->json([
            'ok' => empty($error),
            'redirect_url' => $redirect->getTargetUrl(),
            'flash_success' => $success,
            'flash_error' => $error,
        ]);
    }
}
