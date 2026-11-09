<?php
namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * برای هر درخواست به مسیر api/* همیشه JSON برگردان تا پیام خطا در customer-site دیده شود.
     */
    public function render($request, Throwable $e): Response
    {
        if ($request->is('api/*')) {
            // Part 2 (7-part local audit task, 2026-09-19) follow-up finding:
            // TokenMismatchException does not implement getStatusCode() (it
            // is a plain Exception, not an HttpExceptionInterface — Laravel
            // only converts it to a 419 HttpException later, inside
            // parent::prepareException(), which this api/* branch never
            // reaches). Without this explicit check it fell through to the
            // generic "$status = 500" fallback below, misreporting a CSRF
            // token mismatch as a server error on api/* routes.
            if ($e instanceof TokenMismatchException) {
                return response()->json([
                    'valid' => false,
                    'error' => 'token_mismatch',
                    'message' => 'نشست شما منقضی شده است (خطای امنیتی CSRF). لطفاً دوباره تلاش کنید.',
                ], 419);
            }
            if ($e instanceof ValidationException) {
                return response()->json([
                    'valid' => false,
                    'error' => 'validation',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
            // TICKET-API-TENANT-01 follow-up: neither AuthorizationException
            // nor AuthenticationException implements getStatusCode() (they
            // are not HttpExceptionInterface) — the generic fallback below
            // was silently reporting every Gate::authorize()/policy denial
            // on any api/* route as a 500 "server_error" instead of its
            // real 403/401, regardless of which controller/policy raised
            // it. The underlying access control was never bypassed (no
            // data was ever returned), only the HTTP status/response shape
            // was wrong — still a real defect: a caller (or monitoring)
            // reading the status code could not distinguish "you're not
            // allowed" from "the server is broken".
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'valid' => false,
                    'error' => 'forbidden',
                    'message' => $e->getMessage(),
                ], 403);
            }
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'valid' => false,
                    'error' => 'unauthenticated',
                    'message' => $e->getMessage(),
                ], 401);
            }
            $status = 500;
            if (method_exists($e, 'getStatusCode')) {
                $status = (int) $e->getStatusCode();
            }
            if ($status < 400) {
                $status = 500;
            }
            // An unknown api/* path (NotFoundHttpException, or any abort(404))
            // kept its 404 status above but was still labelled `server_error`
            // with "خطای سرور. بعداً تلاش کنید.". That made a caller using a
            // wrong URL (and anyone reading its logs) chase a server crash
            // that never happened.
            if ($status === 404) {
                return response()->json([
                    'valid' => false,
                    'error' => 'not_found',
                    'message' => 'مسیر یا منبع درخواستی یافت نشد.',
                ], 404);
            }
            $message = config('app.debug')
                ? ($e->getMessage() . ' (در ' . basename($e->getFile()) . ' خط ' . $e->getLine() . ')')
                : 'خطای سرور. بعداً تلاش کنید.';
            return response()->json([
                'valid' => false,
                'error' => 'server_error',
                'message' => $message,
            ], $status);
        }

        // Part 2 (7-part local audit task, 2026-09-19): root cause of the
        // isolated 419 on admin/theme-builder/{theme}/sync-pages (and any
        // other long-lived admin form) is a stale CSRF token — the modal's
        // hidden _token input is baked in at initial page render
        // (resources/views/admin/theme-builder/index.blade.php's static
        // @csrf), and if the session/file-driver session has since expired
        // (SESSION_LIFETIME=120min, config/session.php) or been
        // regenerated, the submitted token no longer matches. This is
        // inherent, correct CSRF behavior — never bypassed here — but
        // Laravel's bare default 419 page left the admin with no
        // explanation and no way back to their in-progress work. Redirect
        // back with the form input preserved (minus the stale token/secret
        // fields) and a clear, visible Persian flash message: the 419
        // status is still what caused this, it is just no longer a
        // silent/confusing dead end.
        if ($e instanceof TokenMismatchException && !$request->expectsJson()) {
            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->with('error', 'نشست شما منقضی شده است (خطای امنیتی CSRF). لطفاً صفحه را تازه‌سازی کرده و دوباره تلاش کنید.');
        }

        return parent::render($request, $e);
    }
}
