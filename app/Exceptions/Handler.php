<?php
namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
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
            $message = config('app.debug')
                ? ($e->getMessage() . ' (در ' . basename($e->getFile()) . ' خط ' . $e->getLine() . ')')
                : 'خطای سرور. بعداً تلاش کنید.';
            return response()->json([
                'valid' => false,
                'error' => 'server_error',
                'message' => $message,
            ], $status);
        }
        return parent::render($request, $e);
    }
}
