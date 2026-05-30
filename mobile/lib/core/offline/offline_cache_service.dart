import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as p;

// ─── Offline cache: persists messages across network loss + app restart ───────
// Uses a simple file-based store (no external DB dependency) so it works
// identically on Android, iOS, Windows, Linux, and macOS.

class OfflineCacheService {
  static OfflineCacheService? _instance;
  static OfflineCacheService get instance => _instance!;

  OfflineCacheService._(this._cacheDir);
  final Directory _cacheDir;

  static Future<void> initialize() async {
    final appDir = await getApplicationDocumentsDirectory();
    final cacheDir = Directory(p.join(appDir.path, 'saas_offline_cache'));
    if (!cacheDir.existsSync()) cacheDir.createSync(recursive: true);
    _instance = OfflineCacheService._(cacheDir);
  }

  // ─── Message cache (read by conversation) ────────────────────────────────

  Future<void> cacheMessages(String conversationId, List<Map<String, dynamic>> messages) async {
    final file = _messageFile(conversationId);
    await file.writeAsString(json.encode(messages));
  }

  Future<List<Map<String, dynamic>>> getCachedMessages(String conversationId) async {
    final file = _messageFile(conversationId);
    if (!file.existsSync()) return [];
    try {
      final raw = json.decode(await file.readAsString()) as List<dynamic>;
      return raw.cast<Map<String, dynamic>>();
    } catch (_) {
      return [];
    }
  }

  // ─── Outbound message queue ───────────────────────────────────────────────

  Future<void> enqueueOutbound(Map<String, dynamic> message) async {
    final queue = await _readQueue();
    queue.add({...message, '_queued_at': DateTime.now().toIso8601String()});
    await _writeQueue(queue);
  }

  Future<List<Map<String, dynamic>>> drainOutboundQueue() async {
    final queue = await _readQueue();
    await _writeQueue([]);
    return queue;
  }

  Future<int> get outboundQueueLength async => (await _readQueue()).length;

  // ─── Conversation list cache ──────────────────────────────────────────────

  Future<void> cacheConversationList(List<Map<String, dynamic>> conversations) async {
    final file = _cacheFile('conversation_list');
    await file.writeAsString(json.encode({
      'data': conversations,
      'cached_at': DateTime.now().toIso8601String(),
    }));
  }

  Future<List<Map<String, dynamic>>> getCachedConversationList() async {
    final file = _cacheFile('conversation_list');
    if (!file.existsSync()) return [];
    try {
      final raw = json.decode(await file.readAsString()) as Map<String, dynamic>;
      // Expire cache after 24h
      final cachedAt = DateTime.tryParse(raw['cached_at'] as String? ?? '');
      if (cachedAt == null || DateTime.now().difference(cachedAt).inHours > 24) return [];
      return (raw['data'] as List<dynamic>).cast<Map<String, dynamic>>();
    } catch (_) {
      return [];
    }
  }

  // ─── Sync on reconnect ────────────────────────────────────────────────────

  Future<bool> hasPendingOutbound() async => (await outboundQueueLength) > 0;

  // ─── Helpers ──────────────────────────────────────────────────────────────

  File _messageFile(String conversationId) =>
      File(p.join(_cacheDir.path, 'msgs_${conversationId.replaceAll('-', '_')}.json'));

  File _cacheFile(String name) => File(p.join(_cacheDir.path, '$name.json'));

  File get _queueFile => File(p.join(_cacheDir.path, 'outbound_queue.json'));

  Future<List<Map<String, dynamic>>> _readQueue() async {
    if (!_queueFile.existsSync()) return [];
    try {
      final raw = json.decode(await _queueFile.readAsString()) as List<dynamic>;
      return raw.cast<Map<String, dynamic>>();
    } catch (_) {
      return [];
    }
  }

  Future<void> _writeQueue(List<Map<String, dynamic>> queue) =>
      _queueFile.writeAsString(json.encode(queue));
}
