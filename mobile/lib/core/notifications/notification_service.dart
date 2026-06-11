import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/client_api_service.dart';
import '../deeplink/conversation_deep_link_service.dart';
import '../realtime/realtime_client_service.dart';

// Background message handler — must be a top-level function (M2.1-c)
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // OS already shows the notification tray entry for background messages.
}

// ─── Provider hierarchy ───────────────────────────────────────────────────────
// Priority 1: Reverb Realtime (WebSocket) — all platforms, always primary
// Priority 2: Firebase FCM (optional, mobile only, background push)
// Priority 3: Local Notification (desktop OS tray / in-app fallback)
// Priority 4: Sync Recovery (offline safety net, all platforms)

final notificationServiceProvider = Provider<NotificationService>((ref) {
  throw StateError('Override notificationServiceProvider in main()');
});

enum NotificationTransport { reverb, firebase, local, syncRecovery }

class NotificationService {
  NotificationService(this._ref);

  final Ref _ref;
  final _localPlugin = FlutterLocalNotificationsPlugin();
  bool _initialized = false;
  NotificationTransport _activeTransport = NotificationTransport.reverb;

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    _bindReverbTransport();
    await _initLocalNotifications();

    if (Platform.isAndroid || Platform.isIOS) {
      await _tryFirebaseTransport();
    }

    _scheduleOfflineSyncRecovery();
  }

  // ── Priority 1: Reverb realtime WebSocket ─────────────────────────────────

  void _bindReverbTransport() {
    final rt = _ref.read(realtimeClientServiceProvider);
    rt.events.listen((event) {
      if (!event.isNotificationEvent) return;
      _activeTransport = NotificationTransport.reverb;
      _showLocal(
        title: event.payload['title'] as String? ?? 'اعلان جدید',
        body: event.payload['body'] as String? ?? '',
        payload: event.payload,
      );
    });
  }

  // ── Priority 2: Firebase FCM (M2.1-c) ────────────────────────────────────
  // Requires google-services.json (Android) + GoogleService-Info.plist (iOS)
  // Falls back silently to Reverb if Firebase is not configured.

  Future<void> _tryFirebaseTransport() async {
    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);

      final messaging = FirebaseMessaging.instance;
      final settings  = await messaging.requestPermission(
        alert: true, badge: true, sound: true,
      );

      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      final token = await messaging.getToken();
      if (token != null) await _registerFcmToken(token);

      messaging.onTokenRefresh.listen(_registerFcmToken);

      // Foreground: Firebase suppresses notification UI — show it manually
      FirebaseMessaging.onMessage.listen((message) {
        _activeTransport = NotificationTransport.firebase;
        final n = message.notification;
        if (n != null) {
          _showLocal(
            title: n.title ?? 'اعلان جدید',
            body: n.body ?? '',
            payload: Map<String, dynamic>.from(message.data),
          );
        }
      });

      // Background tap: app was in background but not terminated
      FirebaseMessaging.onMessageOpenedApp.listen((message) {
        _onTap(NotificationResponse(
          notificationResponseType: NotificationResponseType.selectedNotification,
          payload: message.data.toString(),
          id: 0,
        ));
      });

      _activeTransport = NotificationTransport.firebase;
    } catch (_) {
      // Firebase not configured or google-services.json missing — no-op
    }
  }

  Future<void> _registerFcmToken(String token) async {
    try {
      await _ref.read(clientApiServiceProvider).registerPushToken(token);
    } catch (_) {}
  }

  // ── Priority 3: Local notifications ──────────────────────────────────────

  Future<void> _initLocalNotifications() async {
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios     = DarwinInitializationSettings(
        requestAlertPermission: true,
        requestBadgePermission: true,
        requestSoundPermission: true);
    const linux   = LinuxInitializationSettings(defaultActionName: 'Open');

    await _localPlugin.initialize(
      const InitializationSettings(android: android, iOS: ios, macOS: ios, linux: linux),
      onDidReceiveNotificationResponse: _onTap,
    );
  }

  Future<void> _showLocal({
    required String title,
    required String body,
    Map<String, dynamic> payload = const {},
  }) async {
    await _localPlugin.show(
      payload.hashCode,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails('messages', 'پیام‌ها',
            importance: Importance.high, priority: Priority.high),
        iOS: DarwinNotificationDetails(
            presentAlert: true, presentBadge: true, presentSound: true),
      ),
    );
  }

  void _onTap(NotificationResponse response) {
    final raw = response.payload;
    if (raw == null || raw.isEmpty) return;
    // Dispatch any support:// URI from notification payload to the deep link service
    _ref.read(conversationDeepLinkServiceProvider).handleUri(raw);
  }

  // ── Priority 4: Offline sync recovery ────────────────────────────────────

  void _scheduleOfflineSyncRecovery() {
    // Missed notifications synced by OfflineCacheService.syncOnReconnect()
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  Future<void> syncMissedNotifications() async {
    final api    = _ref.read(clientApiServiceProvider);
    final notifs = await api.getNotifications();
    for (final n in notifs.where((n) => !n.isRead)) {
      await _showLocal(title: n.title, body: n.body);
    }
  }

  NotificationTransport get activeTransport => _activeTransport;
}
