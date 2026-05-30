import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:device_info_plus/device_info_plus.dart' show DeviceInfoPlugin;
import 'package:package_info_plus/package_info_plus.dart';

import '../../core/api/client_api_service.dart';
import '../../core/api/models/auth_models.dart';
import '../../core/security/secure_token_service.dart';

// ─── State ────────────────────────────────────────────────────────────────────

class AuthState {
  const AuthState({this.user, this.isAuthenticated = false});

  final AuthUser? user;
  final bool isAuthenticated;
}

// ─── Notifier ────────────────────────────────────────────────────────────────

class AuthStateNotifier extends StateNotifier<AuthState> {
  AuthStateNotifier(this._api, this._tokens) : super(const AuthState()) {
    _restore();
  }

  final ClientApiService _api;
  final SecureTokenService _tokens;

  Future<void> _restore() async {
    final token = await _tokens.read(SecureKey.accessToken);
    if (token != null) {
      // Attempt a manifest fetch as a lightweight auth check
      try {
        final manifest = await _api.getRuntimeManifest();
        final userId = manifest['user_id'] as String? ?? '';
        final name = manifest['user_name'] as String? ?? '';
        final roles = (manifest['roles'] as List<dynamic>?)?.cast<String>() ?? [];
        state = AuthState(
          isAuthenticated: true,
          user: AuthUser(id: userId, name: name, mobile: '', roles: roles),
        );
      } catch (_) {
        // Token expired or invalid — stay logged out
        await _tokens.deleteAll();
      }
    }
  }

  Future<void> login({required String mobile, required String password}) async {
    final info = await PackageInfo.fromPlatform();
    final deviceId = await _getDeviceId();

    final auth = await _api.login(
      mobile: mobile,
      password: password,
      deviceId: deviceId,
      appVersion: info.version,
    );
    state = AuthState(isAuthenticated: true, user: auth.user);
  }

  Future<void> logout() async {
    await _api.logout();
    state = const AuthState();
  }

  Future<String> _getDeviceId() async {
    final stored = await _tokens.read(SecureKey.deviceId);
    if (stored != null) return stored;

    final plugin = DeviceInfoPlugin();
    String id;
    try {
      // Fallback device ID derivation (platform-agnostic)
      final info = await plugin.deviceInfo;
      id = info.data['identifierForVendor'] as String? ??
          info.data['androidId'] as String? ??
          DateTime.now().millisecondsSinceEpoch.toString();
    } catch (_) {
      id = DateTime.now().millisecondsSinceEpoch.toString();
    }
    await _tokens.write(SecureKey.deviceId, id);
    return id;
  }
}

// ─── Provider ────────────────────────────────────────────────────────────────

final authStateProvider = StateNotifierProvider<AuthStateNotifier, AuthState>((ref) {
  final api = ref.watch(clientApiServiceProvider);
  final tokens = ref.watch(secureTokenServiceProvider);
  return AuthStateNotifier(api, tokens);
});
