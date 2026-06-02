import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final secureTokenServiceProvider = Provider<SecureTokenService>((ref) {
  return SecureTokenService();
});

// ─── Key registry ─────────────────────────────────────────────────────────────

class SecureKey {
  SecureKey._();
  static const String accessToken = 'access_token';
  static const String sessionId = 'session_id';
  static const String tenantId = 'tenant_id';
  static const String userId = 'user_id';
  static const String baseUrl = 'base_url';
  static const String reverbAppKey = 'reverb_app_key';
  static const String deviceId = 'device_id';
  static const String updateChannel    = 'update_channel';
  // PHASE M3.2 — stores RuntimeContext JSON after login
  static const String runtimeContext   = 'runtime_context';
}

// ─── Service ─────────────────────────────────────────────────────────────────

class SecureTokenService {
  SecureTokenService()
      : _storage = const FlutterSecureStorage(
          aOptions: AndroidOptions(encryptedSharedPreferences: true),
          iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
        );

  final FlutterSecureStorage _storage;

  Future<void> write(String key, String value) => _storage.write(key: key, value: value);

  Future<String?> read(String key) => _storage.read(key: key);

  Future<void> delete(String key) => _storage.delete(key: key);

  Future<void> deleteAll() => _storage.deleteAll();

  Future<bool> has(String key) async => (await _storage.read(key: key)) != null;

  // Atomically rotate token (write new, delete old key if renamed)
  Future<void> rotateToken(String newToken) async {
    await _storage.write(key: SecureKey.accessToken, value: newToken);
  }
}
