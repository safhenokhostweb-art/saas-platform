import 'dart:async';
import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

import '../security/secure_token_service.dart';
import '../api/client_api_service.dart';
import 'realtime_event.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final realtimeClientServiceProvider = Provider<RealtimeClientService>((ref) {
  final tokens = ref.watch(secureTokenServiceProvider);
  final api = ref.watch(clientApiServiceProvider);
  return RealtimeClientService(tokenService: tokens, api: api);
});

// ─── Connection state ─────────────────────────────────────────────────────────

enum RealtimeConnectionState { disconnected, connecting, connected, reconnecting, failed }

// ─── Service ─────────────────────────────────────────────────────────────────

class RealtimeClientService {
  RealtimeClientService({required SecureTokenService tokenService, required ClientApiService api})
      : _tokenService = tokenService,
        _api = api;

  final SecureTokenService _tokenService;
  final ClientApiService _api;

  late PusherChannelsFlutter _pusher;
  bool _initialized = false;

  final _stateController = StreamController<RealtimeConnectionState>.broadcast();
  final _eventController = StreamController<RealtimeEvent>.broadcast();

  Stream<RealtimeConnectionState> get connectionState => _stateController.stream;
  Stream<RealtimeEvent> get events => _eventController.stream;

  RealtimeConnectionState _currentState = RealtimeConnectionState.disconnected;

  // ─── Subscribed channels ──────────────────────────────────────────────────

  final Map<String, PusherChannel> _channels = {};
  Timer? _heartbeatTimer;
  Timer? _reconnectTimer;
  int _reconnectAttempts = 0;
  static const int _maxReconnectAttempts = 10;

  // ── Initialize + connect ──────────────────────────────────────────────────

  Future<void> connect() async {
    if (_initialized && _currentState == RealtimeConnectionState.connected) return;

    final baseUrl = await _tokenService.read(SecureKey.baseUrl) ?? 'platform.example.com';
    final token = await _tokenService.read(SecureKey.accessToken) ?? '';
    final appKey = await _tokenService.read(SecureKey.reverbAppKey) ?? 'saas-platform';

    // Strip protocol prefix if present
    final host = baseUrl.replaceAll(RegExp(r'^https?://'), '');

    _pusher = PusherChannelsFlutter.getInstance();

    _setState(RealtimeConnectionState.connecting);

    await _pusher.init(
      apiKey: appKey,
      cluster: 'mt1', // not used by self-hosted Reverb, required by SDK
      onConnectionStateChange: _onConnectionStateChange,
      onError: _onError,
      onEvent: _handlePusherEvent,
      onSubscriptionSucceeded: _onSubscriptionSucceeded,
      // Reverb auth endpoint for private/presence channels
      authEndpoint: 'https://$host/api/mobile/realtime/auth',
      onAuthorizer: (channelName, socketId, options) async {
        return {'auth': await _socketAuth(channelName, socketId, host, token)};
      },
      // Android: use proxy to reach self-hosted Reverb
      proxy: 'ws://$host:8080',
      useTLS: false,
      activityTimeout: 30000,
      pongTimeout: 5000,
    );

    await _pusher.connect();
    _initialized = true;
    _startHeartbeat();
  }

  // ── Socket auth (private/presence channels) ───────────────────────────────

  Future<String> _socketAuth(String channelName, String socketId, String host, String token) async {
    try {
      final manifest = await _api.getRuntimeManifest();
      return manifest['socket_auth'] as String? ?? '';
    } catch (_) {
      return '';
    }
  }

  // ── Channel subscriptions ─────────────────────────────────────────────────

  Future<void> subscribeToConversation(String conversationId) async {
    final channelName = 'private-conversation.$conversationId';
    if (_channels.containsKey(channelName)) return;
    await _pusher.subscribe(channelName: channelName);
  }

  Future<void> subscribeToPresence(String tenantId) async {
    final channelName = 'presence-tenant.$tenantId';
    if (_channels.containsKey(channelName)) return;
    await _pusher.subscribe(channelName: channelName);
  }

  Future<void> subscribeToUserNotifications(String userId) async {
    final channelName = 'private-user.$userId';
    if (_channels.containsKey(channelName)) return;
    await _pusher.subscribe(channelName: channelName);
  }

  Future<void> unsubscribeFromConversation(String conversationId) async {
    final channelName = 'private-conversation.$conversationId';
    await _pusher.unsubscribe(channelName: channelName);
    _channels.remove(channelName);
  }

  // ── Typing indicators ─────────────────────────────────────────────────────

  Future<void> sendTypingStart(String conversationId) async {
    await _pusher.trigger(PusherEvent(
      channelName: 'private-conversation.$conversationId',
      eventName: 'client-typing-start',
      data: json.encode({'conversation_id': conversationId}),
    ));
  }

  Future<void> sendTypingStop(String conversationId) async {
    await _pusher.trigger(PusherEvent(
      channelName: 'private-conversation.$conversationId',
      eventName: 'client-typing-stop',
      data: json.encode({'conversation_id': conversationId}),
    ));
  }

  // ── Read receipt ──────────────────────────────────────────────────────────

  Future<void> sendReadReceipt(String conversationId, String messageId) async {
    await _api.markRead(conversationId, messageId);
    // Also broadcast via client event for instant UI update
    await _pusher.trigger(PusherEvent(
      channelName: 'private-conversation.$conversationId',
      eventName: 'client-read-receipt',
      data: json.encode({'message_id': messageId, 'conversation_id': conversationId}),
    ));
  }

  // ── Event routing ─────────────────────────────────────────────────────────

  void _handlePusherEvent(PusherEvent event) {
    try {
      final payload = event.data != null ? json.decode(event.data!) as Map<String, dynamic> : <String, dynamic>{};
      final rt = RealtimeEvent(
        channel: event.channelName,
        eventName: event.eventName,
        payload: payload,
        receivedAt: DateTime.now(),
      );
      _eventController.add(rt);
    } catch (_) {
      // Malformed event — discard
    }
  }

  void _onSubscriptionSucceeded(String channelName, dynamic data) {
    _channels[channelName] = PusherChannel(channelName: channelName);
  }

  void _onConnectionStateChange(dynamic currentState, dynamic previousState) {
    final state = _parseState(currentState.toString());
    _setState(state);

    if (state == RealtimeConnectionState.connected) {
      _reconnectAttempts = 0;
    } else if (state == RealtimeConnectionState.disconnected) {
      _scheduleReconnect();
    }
  }

  void _onError(String message, int? code, dynamic error) {
    _setState(RealtimeConnectionState.reconnecting);
    _scheduleReconnect();
  }

  RealtimeConnectionState _parseState(String raw) {
    if (raw.contains('CONNECTED')) return RealtimeConnectionState.connected;
    if (raw.contains('CONNECTING')) return RealtimeConnectionState.connecting;
    if (raw.contains('DISCONNECTED')) return RealtimeConnectionState.disconnected;
    if (raw.contains('RECONNECTING')) return RealtimeConnectionState.reconnecting;
    return RealtimeConnectionState.disconnected;
  }

  // ── Heartbeat (keep-alive ping) ───────────────────────────────────────────

  void _startHeartbeat() {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 25), (_) async {
      if (_currentState == RealtimeConnectionState.connected) {
        try {
          await _api.updateMyPresence('online');
        } catch (_) {}
      }
    });
  }

  // ── Reconnect with exponential backoff ────────────────────────────────────

  void _scheduleReconnect() {
    if (_reconnectAttempts >= _maxReconnectAttempts) {
      _setState(RealtimeConnectionState.failed);
      return;
    }
    _reconnectTimer?.cancel();
    final delay = Duration(seconds: (2 << _reconnectAttempts).clamp(2, 60));
    _reconnectAttempts++;
    _setState(RealtimeConnectionState.reconnecting);
    _reconnectTimer = Timer(delay, () async {
      try {
        await _pusher.connect();
      } catch (_) {
        _scheduleReconnect();
      }
    });
  }

  void _setState(RealtimeConnectionState state) {
    _currentState = state;
    _stateController.add(state);
  }

  // ── Public reconnect (called by DesktopProductionRuntime / Android) ──────

  Future<void> reconnect() async {
    _reconnectAttempts = 0;
    try {
      await _pusher.connect();
    } catch (_) {
      _scheduleReconnect();
    }
  }

  // ── Public heartbeat (called by AndroidProductionRuntime) ─────────────────

  Future<void> sendHeartbeat() async {
    if (_currentState == RealtimeConnectionState.connected) {
      try {
        await _api.updateMyPresence('online');
      } catch (_) {}
    }
  }

  // ── Disconnect ────────────────────────────────────────────────────────────

  Future<void> disconnect() async {
    _heartbeatTimer?.cancel();
    _reconnectTimer?.cancel();
    await _pusher.disconnect();
    _channels.clear();
    _setState(RealtimeConnectionState.disconnected);
  }

  void dispose() {
    _heartbeatTimer?.cancel();
    _reconnectTimer?.cancel();
    _stateController.close();
    _eventController.close();
  }
}
