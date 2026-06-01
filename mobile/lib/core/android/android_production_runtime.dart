import 'dart:async';
import 'dart:io';

import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../realtime/realtime_client_service.dart';
import '../offline/offline_sync_service.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final androidProductionRuntimeProvider = Provider<AndroidProductionRuntime>((ref) {
  if (!Platform.isAndroid) return AndroidProductionRuntime.noop();
  final runtime = AndroidProductionRuntime(ref);
  ref.onDispose(runtime.dispose);
  return runtime;
});

// ─── Notification channels ────────────────────────────────────────────────────

class SupportNotificationChannels {
  static const messages = AndroidNotificationChannel(
    'support_messages',
    'Support Messages',
    description:  'Real-time support conversation messages',
    importance:   Importance.max,
    playSound:    true,
    enableVibration: true,
  );

  static const assignments = AndroidNotificationChannel(
    'support_assignments',
    'Conversation Assignments',
    description:  'Conversation assignment and escalation events',
    importance:   Importance.high,
    playSound:    true,
  );

  static const background = AndroidNotificationChannel(
    'support_background',
    'Background Service',
    description:  'Keeps support connection alive',
    importance:   Importance.low,
    playSound:    false,
    enableVibration: false,
    showBadge:    false,
  );

  static const List<AndroidNotificationChannel> all = [
    messages,
    assignments,
    background,
  ];
}

// ─── Foreground service notification ─────────────────────────────────────────

const _foregroundNotificationId = 9001;
const _foregroundNotificationDetails = AndroidNotificationDetails(
  'support_background',
  'Background Service',
  channelDescription: 'Keeps support connection alive',
  importance:         Importance.low,
  priority:           Priority.low,
  ongoing:            true,
  autoCancel:         false,
  showWhen:           false,
  icon:               '@mipmap/ic_launcher',
);

// ─── Runtime ─────────────────────────────────────────────────────────────────

/// Android production runtime — foreground service, notification channels,
/// background sync, and reconnection logic.
class AndroidProductionRuntime {
  AndroidProductionRuntime(this._ref) : _noop = false {
    _init();
  }

  AndroidProductionRuntime.noop()
      : _ref  = null,
        _noop = true;

  final Ref? _ref;
  final bool _noop;

  final _localPlugin = FlutterLocalNotificationsPlugin();
  bool _initialized  = false;
  bool _foregroundActive = false;

  StreamSubscription<SyncState>? _syncSub;
  Timer? _reconnectTimer;
  Timer? _heartbeatTimer;

  // ── Initialize ────────────────────────────────────────────────────────────

  Future<void> _init() async {
    if (_noop || _initialized) return;
    _initialized = true;

    await _createNotificationChannels();
    await _initLocalNotifications();
    await _requestPermissions();
    _startForegroundService();
    _bindBackgroundSync();
    _startHeartbeat();
  }

  // ── Notification channels ─────────────────────────────────────────────────

  Future<void> _createNotificationChannels() async {
    final androidImpl = _localPlugin
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();

    for (final channel in SupportNotificationChannels.all) {
      await androidImpl?.createNotificationChannel(channel);
    }
  }

  Future<void> _initLocalNotifications() async {
    await _localPlugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
      onDidReceiveNotificationResponse: _onNotificationTap,
    );
  }

  Future<void> _requestPermissions() async {
    final androidImpl = _localPlugin
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await androidImpl?.requestNotificationsPermission();
    await androidImpl?.requestExactAlarmsPermission();
  }

  // ── Foreground service (keeps process alive for WebSocket) ────────────────

  void _startForegroundService() {
    if (_foregroundActive) return;
    _foregroundActive = true;

    _localPlugin.show(
      _foregroundNotificationId,
      'Support Client',
      'Keeping your conversations connected',
      const NotificationDetails(android: _foregroundNotificationDetails),
    );
  }

  Future<void> stopForegroundService() async {
    if (!_foregroundActive) return;
    _foregroundActive = false;
    await _localPlugin.cancel(_foregroundNotificationId);
  }

  // ── Show a support notification ───────────────────────────────────────────

  Future<void> showMessageNotification({
    required int    id,
    required String title,
    required String body,
    String?         conversationId,
    String?         channelId,
  }) async {
    if (_noop) return;

    final channel = channelId ?? SupportNotificationChannels.messages.id;
    final details = AndroidNotificationDetails(
      channel,
      channel == SupportNotificationChannels.assignments.id
          ? 'Conversation Assignments'
          : 'Support Messages',
      importance:   Importance.max,
      priority:     Priority.high,
      showWhen:     true,
      groupKey:     'support_conversations',
      autoCancel:   true,
    );

    await _localPlugin.show(
      id,
      title,
      body,
      NotificationDetails(android: details),
      payload: conversationId,
    );
  }

  // ── Background sync ───────────────────────────────────────────────────────

  void _bindBackgroundSync() {
    if (_noop || _ref == null) return;
    try {
      final sync = _ref.read(offlineSyncServiceProvider);
      _syncSub = sync.stateStream.listen((state) {
        if (state.online && !state.syncing) {
          _scheduleReconnect();
        }
      });
    } catch (_) {}
  }

  // ── Heartbeat (keeps WebSocket alive while app is in background) ──────────

  void _startHeartbeat() {
    if (_noop || _ref == null) return;
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      try {
        _ref.read(realtimeClientServiceProvider).sendHeartbeat();
      } catch (_) {}
    });
  }

  // ── Reconnect logic ───────────────────────────────────────────────────────

  void _scheduleReconnect() {
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(const Duration(seconds: 3), () {
      try {
        _ref?.read(realtimeClientServiceProvider).reconnect();
      } catch (_) {}
    });
  }

  // ── Notification tap ──────────────────────────────────────────────────────

  void _onNotificationTap(NotificationResponse response) {
    // Handled by ConversationDeepLinkService via payload
  }

  void dispose() {
    if (_noop) return;
    _syncSub?.cancel();
    _reconnectTimer?.cancel();
    _heartbeatTimer?.cancel();
  }
}
