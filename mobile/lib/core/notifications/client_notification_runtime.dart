import 'dart:async';
import 'dart:io';

import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/client_api_service.dart';
import '../realtime/realtime_client_service.dart';
import '../realtime/realtime_event.dart';
import 'voice_notification_runtime.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final clientNotificationRuntimeProvider = Provider<ClientNotificationRuntime>((ref) {
  final runtime = ClientNotificationRuntime(ref);
  ref.onDispose(runtime.dispose);
  return runtime;
});

// ─── Transport priority ───────────────────────────────────────────────────────

enum NotificationTransportPriority {
  reverb,      // 1. Reverb realtime — primary
  localOs,     // 2. Local OS notification — always secondary
  offlineSync, // 3. Offline sync recovery — last resort
}

// ─── Notification payload ─────────────────────────────────────────────────────

class NotificationPayload {
  const NotificationPayload({
    required this.id,
    required this.title,
    required this.body,
    this.conversationId,
    this.tenantId,
    this.type = 'message',
    this.payload = const {},
  });

  final String id;
  final String title;
  final String body;
  final String? conversationId;
  final String? tenantId;
  final String type;
  final Map<String, dynamic> payload;

  factory NotificationPayload.fromRealtimeEvent(RealtimeEvent event) {
    final p = event.payload;
    return NotificationPayload(
      id:              p['id'] as String? ?? event.eventName,
      title:           p['title'] as String? ?? 'New notification',
      body:            p['body'] as String? ?? '',
      conversationId:  p['conversation_id'] as String?,
      tenantId:        p['tenant_id'] as String?,
      type:            p['type'] as String? ?? 'message',
      payload:         Map<String, dynamic>.from(p),
    );
  }
}

// ─── Tap handler callback ─────────────────────────────────────────────────────

typedef NotificationTapCallback = void Function(NotificationPayload payload);

// ─── Runtime ─────────────────────────────────────────────────────────────────

class ClientNotificationRuntime {
  ClientNotificationRuntime(this._ref) {
    _voiceRuntime = VoiceNotificationRuntime();
  }

  final Ref _ref;
  final _localPlugin = FlutterLocalNotificationsPlugin();
  late final VoiceNotificationRuntime _voiceRuntime;

  bool _initialized = false;
  NotificationTapCallback? _onTap;
  StreamSubscription<RealtimeEvent>? _reverbSub;

  // Active transport for diagnostics
  NotificationTransportPriority _activeTransport = NotificationTransportPriority.reverb;
  NotificationTransportPriority get activeTransport => _activeTransport;

  // ── Initialize ────────────────────────────────────────────────────────────

  Future<void> initialize({NotificationTapCallback? onTap}) async {
    if (_initialized) return;
    _initialized = true;
    _onTap       = onTap;

    await _initLocalNotifications();
    await _voiceRuntime.initialize();

    // Priority 1 — Reverb realtime
    _bindReverbTransport();

    // Priority 3 — Offline sync recovery (fires on reconnect)
    _scheduleOfflineSyncRecovery();
  }

  // ── Show a notification from external trigger (e.g., FCM, deep link) ──────

  Future<void> showNotification(NotificationPayload payload) async {
    await _showLocalOs(payload);
    await _voiceRuntime.playForType(payload.type);
  }

  // ── Register tap callback ─────────────────────────────────────────────────

  void setOnTap(NotificationTapCallback cb) => _onTap = cb;

  // ── Transport status ──────────────────────────────────────────────────────

  bool get isReverbActive => _activeTransport == NotificationTransportPriority.reverb;

  // ── Sync missed (called on reconnect) ────────────────────────────────────

  Future<void> syncMissed() async {
    try {
      final api   = _ref.read(clientApiServiceProvider);
      final notifs = await api.getNotifications();
      for (final n in notifs.where((n) => !n.isRead)) {
        await _showLocalOs(NotificationPayload(
          id:    n.id,
          title: n.title,
          body:  n.body,
          type:  'sync_recovery',
        ));
      }
      _activeTransport = NotificationTransportPriority.offlineSync;
    } catch (_) {}
  }

  void dispose() {
    _reverbSub?.cancel();
    _voiceRuntime.dispose();
  }

  // ── Priority 1: Reverb realtime ───────────────────────────────────────────

  void _bindReverbTransport() {
    try {
      final rt = _ref.read(realtimeClientServiceProvider);
      _reverbSub = rt.events.listen((event) {
        if (!event.isNotificationEvent) return;
        _activeTransport = NotificationTransportPriority.reverb;

        final payload = NotificationPayload.fromRealtimeEvent(event);
        _showLocalOs(payload);
        _voiceRuntime.playForType(payload.type);
      });
    } catch (_) {}
  }

  // ── Priority 2: Local OS notification ────────────────────────────────────

  Future<void> _initLocalNotifications() async {
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios     = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );
    const linux = LinuxInitializationSettings(defaultActionName: 'Open');

    await _localPlugin.initialize(
      const InitializationSettings(android: android, iOS: ios, macOS: ios, linux: linux),
      onDidReceiveNotificationResponse: _handleTap,
    );

    // Request Android 13+ permissions
    if (Platform.isAndroid) {
      final android = _localPlugin
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      await android?.requestNotificationsPermission();
    }
  }

  Future<void> _showLocalOs(NotificationPayload payload) async {
    _activeTransport = NotificationTransportPriority.localOs;

    const androidDetails = AndroidNotificationDetails(
      'support_messages',
      'Support Messages',
      channelDescription: 'Real-time support conversation notifications',
      importance: Importance.max,
      priority: Priority.high,
      showWhen: true,
      enableVibration: true,
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    await _localPlugin.show(
      payload.id.hashCode,
      payload.title,
      payload.body,
      const NotificationDetails(android: androidDetails, iOS: iosDetails, macOS: iosDetails),
      payload: '${payload.conversationId}|${payload.tenantId}|${payload.type}',
    );
  }

  void _handleTap(NotificationResponse response) {
    final raw = response.payload ?? '';
    final parts = raw.split('|');
    final conversationId = parts.isNotEmpty ? parts[0] : null;
    final tenantId       = parts.length > 1  ? parts[1] : null;
    final type           = parts.length > 2  ? parts[2] : 'message';

    _onTap?.call(NotificationPayload(
      id:              response.id.toString(),
      title:           '',
      body:            '',
      conversationId:  conversationId,
      tenantId:        tenantId,
      type:            type,
    ));
  }

  // ── Priority 3: Offline sync recovery ────────────────────────────────────

  void _scheduleOfflineSyncRecovery() {
    // OfflineSyncService triggers syncMissed() on reconnect
    // This hook is left as the integration point
  }
}
