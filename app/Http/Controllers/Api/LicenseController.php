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
            'domain' => 'required|string|max:255',
        ]);

        $headerCredential = (string) ($request->bearerToken() ?? '');
        $legacyCredential = (string) $request->input('license_key', '');
        if ($headerCredential !== '' && $legacyCredential !== '' && ! hash_equals($headerCredential, $legacyCredential)) {
            return response()->json(['valid' => false, 'error' => 'conflicting_credentials'], 401);
        }
        $licenseKey = $headerCredential !== '' ? $headerCredential : $legacyCredential;
        if ($licenseKey === '') {
            return response()->json(['valid' => false, 'error' => 'missing_credentials'], 401);
        }

        if ($headerCredential === '' && $legacyCredential !== '') {
            Log::warning('DEPRECATED_CREDENTIAL_TRANSPORT', [
                'source' => $request->query->has('license_key') ? 'query' : 'body',
                'endpoint' => 'license.validate',
            ]);
        }

        try {
            $result = $this->licenseService->validate(
                $licenseKey,
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
