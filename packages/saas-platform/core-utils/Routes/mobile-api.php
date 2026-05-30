<?php
/**
 * Mobile Runtime API Routes — Phase M1.0
 * Self-Hosted Mobile Communication Runtime Foundation
 *
 * Load in RouteServiceProvider under prefix /api/mobile with middleware api,auth:sanctum
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileRuntimeManifestController;
use App\Http\Controllers\Api\Mobile\MobileConversationController;
use App\Http\Controllers\Api\Mobile\MobilePresenceController;
use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Http\Controllers\Api\Mobile\MobileDeviceController;
use App\Http\Controllers\Api\Mobile\MobileOtaController;
use App\Http\Controllers\Api\Mobile\MobileRealtimeController;

// ─── Public routes (no auth) ────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('token',   [MobileAuthController::class, 'token']);
    Route::post('refresh', [MobileAuthController::class, 'refresh']);
});

// ─── Authenticated routes ────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth management
    Route::post('auth/logout',     [MobileAuthController::class, 'logout']);
    Route::post('auth/logout-all', [MobileAuthController::class, 'logoutAll']);

    // Runtime manifest (self-hosted governance — app downloads on launch)
    Route::get('runtime/manifest', [MobileRuntimeManifestController::class, 'index']);

    // Conversations
    Route::get('conversations',                           [MobileConversationController::class, 'index']);
    Route::post('conversations',                          [MobileConversationController::class, 'store']);
    Route::get('conversations/{id}',                      [MobileConversationController::class, 'show']);
    Route::get('conversations/{id}/messages',             [MobileConversationController::class, 'messages']);
    Route::post('conversations/{id}/messages',            [MobileConversationController::class, 'sendMessage']);
    Route::post('conversations/{id}/read',                [MobileConversationController::class, 'markRead']);
    Route::post('conversations/{id}/archive',             [MobileConversationController::class, 'archive']);

    // Presence & Typing
    Route::post('presence/heartbeat',                             [MobilePresenceController::class, 'heartbeat']);
    Route::get('presence/{userId}',                               [MobilePresenceController::class, 'getPresence']);
    Route::get('conversations/{conversationId}/presence',         [MobilePresenceController::class, 'getConversationPresence']);
    Route::post('conversations/{conversationId}/typing/start',    [MobilePresenceController::class, 'startTyping']);
    Route::post('conversations/{conversationId}/typing/stop',     [MobilePresenceController::class, 'stopTyping']);

    // Notifications
    Route::get('notifications',                     [MobileNotificationController::class, 'index']);
    Route::post('notifications/{id}/read',          [MobileNotificationController::class, 'markRead']);
    Route::post('notifications/read-all',           [MobileNotificationController::class, 'markAllRead']);
    Route::post('notifications/push-token',         [MobileNotificationController::class, 'registerPushToken']);
    Route::delete('notifications/push-token',       [MobileNotificationController::class, 'unregisterPushToken']);
    Route::get('notifications/preferences',         [MobileNotificationController::class, 'getPreferences']);
    Route::put('notifications/preferences',         [MobileNotificationController::class, 'updatePreferences']);

    // Device governance
    Route::post('devices',                  [MobileDeviceController::class, 'register']);
    Route::delete('devices/{deviceId}',     [MobileDeviceController::class, 'unregister']);
    Route::get('devices/{deviceId}',        [MobileDeviceController::class, 'getInfo']);
    Route::put('devices/{deviceId}',        [MobileDeviceController::class, 'updateInfo']);

    // OTA
    Route::get('ota/manifest',              [MobileOtaController::class, 'manifest']);
    Route::post('ota/check',                [MobileOtaController::class, 'checkUpdate']);
    Route::get('ota/bundles/{bundleId}',    [MobileOtaController::class, 'getBundle']);

    // Realtime (Reverb / WebSocket)
    Route::get('realtime/config',           [MobileRealtimeController::class, 'getConnectionConfig']);
    Route::post('realtime/auth',            [MobileRealtimeController::class, 'authorizeChannel']);
    Route::get('realtime/stats',            [MobileRealtimeController::class, 'getStats']);
});
