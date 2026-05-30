import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:connectivity_plus/connectivity_plus.dart';

import 'offline_cache_service.dart';
import '../api/client_api_service.dart';
import '../notifications/notification_service.dart';

final offlineSyncServiceProvider = Provider<OfflineSyncService>((ref) {
  final api = ref.watch(clientApiServiceProvider);
  final notifications = ref.watch(notificationServiceProvider);
  return OfflineSyncService(api: api, notifications: notifications);
});

// Watches connectivity changes and flushes the outbound queue when back online.
class OfflineSyncService {
  OfflineSyncService({required ClientApiService api, required NotificationService notifications})
      : _api = api,
        _notifications = notifications {
    _watchConnectivity();
  }

  final ClientApiService _api;
  final NotificationService _notifications;
  bool _online = true;

  void _watchConnectivity() {
    Connectivity().onConnectivityChanged.listen((results) async {
      final wasOffline = !_online;
      _online = results.any((r) => r != ConnectivityResult.none);

      if (_online && wasOffline) {
        await _sync();
      }
    });
  }

  Future<void> _sync() async {
    // Drain outbound message queue
    final cache = OfflineCacheService.instance;
    final pending = await cache.drainOutboundQueue();
    for (final msg in pending) {
      try {
        final convId = msg['conversation_id'] as String;
        final content = msg['content'] as String;
        final type = msg['type'] as String? ?? 'text';
        await _api.sendMessage(convId, content, type: type);
      } catch (_) {
        // Re-enqueue on failure
        await cache.enqueueOutbound(msg);
      }
    }

    // Sync missed notifications
    await _notifications.syncMissedNotifications();
  }
}
