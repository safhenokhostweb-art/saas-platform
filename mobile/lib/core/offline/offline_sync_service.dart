import 'dart:async';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'offline_cache_service.dart';
import '../api/client_api_service.dart';
import '../api/attachment_upload_service.dart';
import '../notifications/client_notification_runtime.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final offlineSyncServiceProvider = Provider<OfflineSyncService>((ref) {
  final service = OfflineSyncService(
    api:           ref.watch(clientApiServiceProvider),
    notifications: ref.watch(clientNotificationRuntimeProvider),
    attachments:   ref.watch(attachmentUploadServiceProvider),
  );
  ref.onDispose(service.dispose);
  return service;
});

// ─── Sync state ───────────────────────────────────────────────────────────────

class SyncState {
  const SyncState({
    this.online = true,
    this.syncing = false,
    this.pendingMessages = 0,
    this.pendingAttachments = 0,
    this.lastSyncAt,
    this.lastError,
  });

  final bool online;
  final bool syncing;
  final int pendingMessages;
  final int pendingAttachments;
  final DateTime? lastSyncAt;
  final String? lastError;

  SyncState copyWith({
    bool? online,
    bool? syncing,
    int? pendingMessages,
    int? pendingAttachments,
    DateTime? lastSyncAt,
    String? lastError,
  }) =>
      SyncState(
        online:             online             ?? this.online,
        syncing:            syncing            ?? this.syncing,
        pendingMessages:    pendingMessages    ?? this.pendingMessages,
        pendingAttachments: pendingAttachments ?? this.pendingAttachments,
        lastSyncAt:         lastSyncAt         ?? this.lastSyncAt,
        lastError:          lastError,
      );
}

// ─── Service ─────────────────────────────────────────────────────────────────

/// Complete offline sync — queues messages and attachments, retries on reconnect,
/// and applies conflict resolution (server wins for messages; skip on 409).
class OfflineSyncService {
  OfflineSyncService({
    required ClientApiService api,
    required ClientNotificationRuntime notifications,
    required AttachmentUploadService attachments,
  })  : _api = api,
        _notifications = notifications,
        _attachments = attachments {
    _watchConnectivity();
  }

  final ClientApiService _api;
  final ClientNotificationRuntime _notifications;
  final AttachmentUploadService _attachments;

  bool _online = true;
  bool _syncing = false;
  SyncState _state = const SyncState();

  final _stateController = StreamController<SyncState>.broadcast();
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  Stream<SyncState> get stateStream => _stateController.stream;
  SyncState get state => _state;
  bool get isOnline => _online;

  // ── Enqueue a message for delivery when online ────────────────────────────

  Future<void> enqueueMessage(Map<String, dynamic> message) async {
    final cache = OfflineCacheService.instance;
    await cache.enqueueOutbound(message);
    _emit(_state.copyWith(pendingMessages: _state.pendingMessages + 1));

    // Attempt immediate delivery if online
    if (_online && !_syncing) {
      await _syncMessages();
    }
  }

  /// Enqueue a file attachment for upload when online.
  Future<void> enqueueAttachment({
    required String conversationId,
    required String filePath,
    required String mimeType,
  }) async {
    final cache = OfflineCacheService.instance;
    await cache.enqueueOutbound({
      '__type':         'attachment',
      'conversation_id': conversationId,
      'file_path':      filePath,
      'mime_type':      mimeType,
      'queued_at':      DateTime.now().toIso8601String(),
    });
    _emit(_state.copyWith(pendingAttachments: _state.pendingAttachments + 1));

    if (_online && !_syncing) {
      await _syncAttachments();
    }
  }

  // ── Force a full sync (call on manual retry or reconnect) ─────────────────

  Future<void> syncNow() async {
    if (_syncing) return;
    await _sync();
  }

  // ── Pending counts ────────────────────────────────────────────────────────

  Future<int> getPendingMessageCount() async {
    final all = await OfflineCacheService.instance.drainOutboundQueue();
    // Re-enqueue everything — we just want the count
    for (final m in all) {
      await OfflineCacheService.instance.enqueueOutbound(m);
    }
    return all.where((m) => m['__type'] != 'attachment').length;
  }

  void dispose() {
    _connectivitySub?.cancel();
    _stateController.close();
  }

  // ── Private ───────────────────────────────────────────────────────────────

  void _watchConnectivity() {
    _connectivitySub = Connectivity().onConnectivityChanged.listen((results) async {
      final wasOffline = !_online;
      _online = results.any((r) => r != ConnectivityResult.none);
      _emit(_state.copyWith(online: _online));

      if (_online && wasOffline) {
        await _sync();
      }
    });
  }

  Future<void> _sync() async {
    if (_syncing) return;
    _syncing = true;
    _emit(_state.copyWith(syncing: true, lastError: null));

    try {
      await _syncMessages();
      await _syncAttachments();
      await _notifications.syncMissed();
      _emit(_state.copyWith(syncing: false, lastSyncAt: DateTime.now()));
    } catch (e) {
      _emit(_state.copyWith(syncing: false, lastError: e.toString()));
    } finally {
      _syncing = false;
    }
  }

  Future<void> _syncMessages() async {
    final cache   = OfflineCacheService.instance;
    final pending = await cache.drainOutboundQueue();
    final failed  = <Map<String, dynamic>>[];

    for (final item in pending) {
      // Skip attachment entries
      if (item['__type'] == 'attachment') {
        failed.add(item);
        continue;
      }

      try {
        final convId  = item['conversation_id'] as String;
        final content = item['content'] as String;
        final type    = item['type'] as String? ?? 'text';

        await _api.sendMessage(convId, content, type: type);
      } on Exception catch (e) {
        final statusCode = _extractStatusCode(e);

        if (statusCode == 409) {
          // Conflict — server already has this message (server wins)
          continue;
        }
        // Re-enqueue on transient errors
        failed.add(item);
      }
    }

    // Re-enqueue failures with incremented retry count
    for (final item in failed) {
      final retries = (item['_retry_count'] as int? ?? 0) + 1;
      if (retries <= 10) {
        await cache.enqueueOutbound({...item, '_retry_count': retries});
      }
    }

    final msgCount = failed.where((m) => m['__type'] != 'attachment').length;
    _emit(_state.copyWith(pendingMessages: msgCount));
  }

  Future<void> _syncAttachments() async {
    final cache   = OfflineCacheService.instance;
    final pending = await cache.drainOutboundQueue();
    final failed  = <Map<String, dynamic>>[];

    for (final item in pending) {
      if (item['__type'] != 'attachment') {
        failed.add(item); // Put non-attachment items back
        continue;
      }

      try {
        final convId   = item['conversation_id'] as String;
        final filePath = item['file_path'] as String;
        final file     = File(filePath);

        if (!await file.exists()) continue; // File deleted — skip silently

        await _attachments.upload(convId, file);
      } on AttachmentValidationException {
        // Invalid file — skip permanently
      } catch (_) {
        final retries = (item['_retry_count'] as int? ?? 0) + 1;
        if (retries <= 5) {
          failed.add({...item, '_retry_count': retries});
        }
      }
    }

    for (final item in failed) {
      await cache.enqueueOutbound(item);
    }

    final attCount = failed.where((m) => m['__type'] == 'attachment').length;
    _emit(_state.copyWith(pendingAttachments: attCount));
  }

  int? _extractStatusCode(Exception e) {
    final msg = e.toString();
    final match = RegExp(r'(\d{3})').firstMatch(msg);
    return match != null ? int.tryParse(match.group(1) ?? '') : null;
  }

  void _emit(SyncState state) {
    _state = state;
    _stateController.add(state);
  }
}
