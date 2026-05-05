<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseServerService $licenseService
    ) {}

    /**
     * اعتبارسنجی لایسنس از طرف customer-site.
     * POST /api/license/validate
     */
    public function validateLicense(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
        ]);

        try {
            $result = $this->licenseService->validate(
                $request->input('license_key'),
                $request->input('domain')
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('License API exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $message = config('app.debug')
                ? 'خطای سرور: ' . $e->getMessage()
                : 'خطای سرور لایسنس. بعداً تلاش کنید.';
            return response()->json([
                'valid' => false,
                'error' => 'server_error',
                'message' => $message,
            ], 500);
        }
    }
}
