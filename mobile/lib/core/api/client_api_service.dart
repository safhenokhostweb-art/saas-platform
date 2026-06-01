import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
export 'package:dio/dio.dart' show FormData, MultipartFile, Options, DioMediaType;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../security/secure_token_service.dart';
import 'models/conversation_models.dart';
import 'models/notification_models.dart';
import 'models/presence_models.dart';
import 'models/ota_models.dart';
import 'models/auth_models.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final clientApiServiceProvider = Provider<ClientApiService>((ref) {
  final tokenService = ref.watch(secureTokenServiceProvider);
  return ClientApiService(tokenService: tokenService);
});

// ─── Service ─────────────────────────────────────────────────────────────────

class ClientApiService {
  ClientApiService({required SecureTokenService tokenService})
      : _tokenService = tokenService {
    _dio = _buildDio();
    _attachInterceptors();
  }

  final SecureTokenService _tokenService;
  late final Dio _dio;

  // ── Base URL is tenant-aware, loaded from secure storage ──────────────────

  Future<String> get _baseUrl async {
    final url = await _tokenService.read(SecureKey.baseUrl);
    return url ?? 'https://platform.example.com';
  }

  // ── Build Dio instance ────────────────────────────────────────────────────

  Dio _buildDio() {
    return Dio(BaseOptions(
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Client-Platform': _platformName(),
        'X-Client-Version': '1.0.0',
      },
    ));
  }

  void _attachInterceptors() {
    _dio.interceptors.addAll([
      _AuthInterceptor(this),
      LogInterceptor(requestBody: false, responseBody: false),
    ]);
  }

  String _platformName() {
    if (Platform.isAndroid) return 'android';
    if (Platform.isIOS) return 'ios';
    if (Platform.isWindows) return 'windows';
    if (Platform.isLinux) return 'linux';
    if (Platform.isMacOS) return 'macos';
    return 'unknown';
  }

  // ─── Auth ─────────────────────────────────────────────────────────────────

  Future<AuthResponse> login({
    required String mobile,
    required String password,
    required String deviceId,
    required String appVersion,
  }) async {
    final base = await _baseUrl;
    final resp = await _dio.post(
      '$base/api/mobile/auth/token',
      data: {
        'mobile': mobile,
        'password': password,
        'device_id': deviceId,
        'platform': _platformName(),
        'app_version': appVersion,
      },
    );
    final auth = AuthResponse.fromJson(resp.data['data'] as Map<String, dynamic>);
    await _tokenService.write(SecureKey.accessToken, auth.token);
    await _tokenService.write(SecureKey.sessionId, auth.session['session_id'] as String? ?? '');
    await _tokenService.write(SecureKey.tenantId, resp.data['meta']?['tenant_id'] as String? ?? '');
    return auth;
  }

  Future<void> refreshToken() async {
    final sessionId = await _tokenService.read(SecureKey.sessionId);
    if (sessionId == null) throw Exception('No session to refresh');
    final base = await _baseUrl;
    final resp = await _dio.post(
      '$base/api/mobile/auth/refresh',
      data: {'session_id': sessionId},
    );
    final newToken = resp.data['data']?['token'] as String?;
    if (newToken != null) await _tokenService.write(SecureKey.accessToken, newToken);
  }

  Future<void> logout() async {
    final sessionId = await _tokenService.read(SecureKey.sessionId);
    if (sessionId != null) {
      final base = await _baseUrl;
      await _dio.post('$base/api/mobile/auth/logout', data: {'session_id': sessionId});
    }
    await _tokenService.deleteAll();
  }

  // ─── Conversations ────────────────────────────────────────────────────────

  Future<List<ConversationSummary>> getConversations() async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/mobile/conversations');
    final list = resp.data['data'] as List<dynamic>;
    return list.map((e) => ConversationSummary.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<ConversationDetail> getConversation(String id) async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/mobile/conversations/$id');
    return ConversationDetail.fromJson(resp.data['data'] as Map<String, dynamic>);
  }

  Future<MessagePage> getMessages(String conversationId, {String? cursor}) async {
    final base = await _baseUrl;
    final resp = await _dio.get(
      '$base/api/mobile/conversations/$conversationId/messages',
      queryParameters: cursor != null ? {'cursor': cursor} : null,
    );
    return MessagePage.fromJson(resp.data as Map<String, dynamic>);
  }

  Future<ConversationMessage> sendMessage(String conversationId, String content, {String type = 'text'}) async {
    final base = await _baseUrl;
    final resp = await _dio.post(
      '$base/api/mobile/conversations/$conversationId/messages',
      data: {'content': content, 'type': type},
    );
    return ConversationMessage.fromJson(resp.data['data'] as Map<String, dynamic>);
  }

  Future<void> markRead(String conversationId, String messageId) async {
    final base = await _baseUrl;
    await _dio.post(
      '$base/api/mobile/conversations/$conversationId/read',
      data: {'message_id': messageId},
    );
  }

  Future<ConversationDetail> createConversation({
    required List<String> participants,
    String type = 'support',
    String? title,
  }) async {
    final base = await _baseUrl;
    final resp = await _dio.post('$base/api/mobile/conversations', data: {
      'participants': participants,
      'type': type,
      if (title != null) 'title': title,
    });
    return ConversationDetail.fromJson(resp.data['data'] as Map<String, dynamic>);
  }

  // ─── Conversation merge ───────────────────────────────────────────────────

  Future<MergePermission> getMyMergePermission() async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/mobile/conversations/merge/my-permission');
    return MergePermission.fromJson(resp.data['data'] as Map<String, dynamic>);
  }

  Future<ConversationMergeResult> mergeConversations({
    required String targetId,
    required List<String> sourceIds,
  }) async {
    final base = await _baseUrl;
    final resp = await _dio.post(
      '$base/api/mobile/conversations/$targetId/merge',
      data: {'source_ids': sourceIds},
    );
    return ConversationMergeResult.fromJson(resp.data['data'] as Map<String, dynamic>);
  }

  // ─── Notifications ────────────────────────────────────────────────────────

  Future<List<AppNotification>> getNotifications() async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/notifications');
    final list = resp.data['data'] as List<dynamic>;
    return list.map((e) => AppNotification.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> markNotificationRead(String id) async {
    final base = await _baseUrl;
    await _dio.post('$base/api/notifications/$id/read');
  }

  Future<void> markAllNotificationsRead() async {
    final base = await _baseUrl;
    await _dio.post('$base/api/notifications/mark-all-read');
  }

  Future<void> updatePushToken(String token, String provider) async {
    final base = await _baseUrl;
    await _dio.post('$base/api/mobile/notifications/register-push', data: {
      'push_token': token,
      'provider': provider,
    });
  }

  // ─── Presence ─────────────────────────────────────────────────────────────

  Future<Map<String, PresenceStatus>> getPresence(List<String> userIds) async {
    final base = await _baseUrl;
    final resp = await _dio.post('$base/api/mobile/presence/status', data: {'user_ids': userIds});
    final map = resp.data['data'] as Map<String, dynamic>;
    return map.map((k, v) => MapEntry(k, PresenceStatus.fromJson(v as Map<String, dynamic>)));
  }

  Future<void> updateMyPresence(String status) async {
    final base = await _baseUrl;
    await _dio.post('$base/api/mobile/presence/update', data: {'status': status});
  }

  // ─── OTA ─────────────────────────────────────────────────────────────────

  Future<OtaManifest> getOtaManifest(String currentVersion, String channel) async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/mobile/ota/manifest', queryParameters: {
      'version': currentVersion,
      'channel': channel,
    });
    return OtaManifest.fromJson(resp.data['data'] as Map<String, dynamic>);
  }

  // ─── Attachment upload (multipart) ───────────────────────────────────────

  Future<Map<String, dynamic>> uploadAttachment(
    String conversationId,
    FormData formData, {
    void Function(int, int)? onProgress,
  }) async {
    final base = await _baseUrl;
    final resp = await _dio.post(
      '$base/api/mobile/conversations/$conversationId/attachments',
      data: formData,
      options: Options(headers: {'Content-Type': 'multipart/form-data'}),
      onSendProgress: onProgress,
    );
    return resp.data['data'] as Map<String, dynamic>;
  }

  // ─── Dashboard ───────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getDashboardStats() async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/mobile/dashboard');
    return resp.data['data'] as Map<String, dynamic>? ?? {};
  }

  // ─── Runtime manifest (feature flags + capabilities) ─────────────────────

  Future<Map<String, dynamic>> getRuntimeManifest() async {
    final base = await _baseUrl;
    final resp = await _dio.get('$base/api/mobile/runtime/manifest');
    return resp.data['data'] as Map<String, dynamic>;
  }

  // ─── Auth header helper (used by interceptor) ────────────────────────────

  Future<String?> get accessToken => _tokenService.read(SecureKey.accessToken);
}

// ─── Auth interceptor (automatic token refresh) ───────────────────────────────

class _AuthInterceptor extends Interceptor {
  _AuthInterceptor(this._api);
  final ClientApiService _api;

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await _api.accessToken;
    if (token != null) options.headers['Authorization'] = 'Bearer $token';
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      try {
        await _api.refreshToken();
        final token = await _api.accessToken;
        final opts = err.requestOptions..headers['Authorization'] = 'Bearer $token';
        final retried = await _api._dio.fetch(opts);
        handler.resolve(retried);
        return;
      } catch (_) {
        // Refresh failed — propagate 401 so UI can show login
      }
    }
    handler.next(err);
  }
}
