import 'dart:io';

import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/client_api_service.dart';
import '../realtime/realtime_client_service.dart';
import '../realtime/realtime_event.dart';
import '../offline/offline_cache_service.dart';

// ─── Provider hierarchy (matches config/mobile.php notification_transport) ───
// Priority 1: Reverb Realtime (WebSocket) — all platforms
// Priority 2: Firebase Push (optional, mobile only)
// Priority 3: Local Notification (desktop OS notifications)
// Priority 4: Sync Recovery (offline, all platforms, last resort)

final notificationServiceProvider = Provider<NotificationService>((ref) {
  throw StateError('Override notificationServiceProvider in main()');
});

// ─── Transport enum ───────────────────────────────────────────────────────────

enum NotificationTransport { reverb, firebase, local, syncRecovery }

// ─── Service ─────────────────────────────────────────────────────────────────

class NotificationService {
  NotificationService(this._ref);

  final Ref _ref;
  final _localPlugin = FlutterLocalNotificationsPlugin();
  bool _initialized = false;
  NotificationTransport _activeTransport = NotificationTransport.reverb;

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    // 1. Try Reverb (always primary)
    _bindReverbTransport();

    // 2. Local notifications (desktop OS tray / mobile fallback)
    await _initLocalNotifications();

    // 3. Firebase Push — optional, mobile only, injected if available
    if (Platform.isAndroid || Platform.isIOS) {
      _tryFirebaseTransport();
    }

    // 4. Sync recovery — always registered as safety net
    _scheduleOfflineSyncRecovery();
  }

  // ── Priority 1: Reverb realtime ───────────────────────────────────────────

  void _bindReverbTransport() {
    final rt = _ref.read(realtimeClientServiceProvider);
    rt.events.listen((event) {
      if (!event.isNotificationEvent) return;
      _activeTransport = NotificationTransport.reverb;
      _showLocal(
        title: event.payload['title'] as String? ?? 'New notification',
        body: event.payload['body'] as String? ?? '',
        payload: event.payload,
      );
    });
  }

  // ── Priority 2: Firebase (optional carrier) ───────────────────────────────

  void _tryFirebaseTransport() {
    // Firebase is OPTIONAL. When firebase_messaging is available via separate
    // integration, inject its token into the backend via updatePushToken().
    // This stub lets the system operate fully without Firebase.
    //
    // To enable: add firebase_messaging to pubspec.yaml and call:
    //   final token = await FirebaseMessaging.instance.getToken();
    //   await _ref.read(clientApiServiceProvider).updatePushToken(token!, 'fcm');
  }

  // ── Priority 3: Local notifications ──────────────────────────────────────

  Future<void> _initLocalNotifications() async {
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings(requestAlertPermission: true, requestBadgePermission: true, requestSoundPermission: true);
    const linux = LinuxInitializationSettings(defaultActionName: 'Open');

    await _localPlugin.initialize(
      const InitializationSettings(android: android, iOS: ios, macOS: ios, linux: linux),
      onDidReceiveNotificationResponse: _onTap,
    );
  }

  Future<void> _showLocal({required String title, required String body, Map<String, dynamic> payload = const {}}) async {
    await _localPlugin.show(
      payload.hashCode,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails('messages', 'Messages', importance: Importance.high, priority: Priority.high),
        iOS: DarwinNotificationDetails(presentAlert: true, presentBadge: true, presentSound: true),
      ),
    );
  }

  void _onTap(NotificationResponse response) {
    // Navigate to conversation from payload
  }

  // ── Priority 4: Offline sync recovery ────────────────────────────────────

  void _scheduleOfflineSyncRecovery() {
    // When app comes back online, sync missed notifications from API
    // Handled by OfflineCacheService.syncOnReconnect()
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  Future<void> syncMissedNotifications() async {
    final api = _ref.read(clientApiServiceProvider);
    final notifs = await api.getNotifications();
    for (final n in notifs.where((n) => !n.isRead)) {
      await _showLocal(title: n.title, body: n.body);
    }
  }

  NotificationTransport get activeTransport => _activeTransport;
}
