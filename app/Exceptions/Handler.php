<?php
namespace App\Exceptions;

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
