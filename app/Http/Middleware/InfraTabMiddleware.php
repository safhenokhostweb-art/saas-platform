<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InfraTabMiddleware
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if ($request->header('X-Infra-Tab') !== '1' && !$request->boolean('_infra_tab')) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            $session = $request->hasSession() ? $request->session() : null;
            $success = $session?->get('success');
            $error = $session?->get('error');
            if ($session) {
                $session->forget(['success', 'error']);
            }

            return response()->json([
                'ok' => empty($error),
                'redirect_url' => $response->getTargetUrl(),
                'flash_success' => $success,
                'flash_error' => $error,
            ]);
        }

        if ($response instanceof Response && $response->getOriginalContent() instanceof View) {
            /** @var View $view */
            $view = $response->getOriginalContent();
            $sections = $view->renderSections();

            return response()->json([
                'ok' => true,
                'html' => $sections['content'] ?? $view->render(),
                'head' => $sections['head'] ?? '',
                'scripts' => $sections['scripts'] ?? '',
            ]);
        }

        return $response;
    }
}
