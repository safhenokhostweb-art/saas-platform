import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../security/secure_token_service.dart';
import 'runtime_mode.dart';

// ─── Providers ───────────────────────────────────────────────────────────────

final runtimeSelectorServiceProvider = Provider<RuntimeSelectorService>((ref) {
  final tokenService = ref.watch(secureTokenServiceProvider);
  return RuntimeSelectorService(tokenService: tokenService);
});

final currentRuntimeModeProvider = FutureProvider<RuntimeMode>((ref) async {
  final service = ref.watch(runtimeSelectorServiceProvider);
  return service.currentMode();
});

final currentRuntimeContextProvider = FutureProvider<RuntimeContext>((ref) async {
  final service = ref.watch(runtimeSelectorServiceProvider);
  return service.currentContext();
});

// ─── RuntimeContext ───────────────────────────────────────────────────────────

/// Full context resolved after login. Immutable per session.
class RuntimeContext {
  const RuntimeContext({
    required this.mode,
    required this.platformUrl,
    required this.tenantUrl,
    required this.userId,
    required this.userType,
    required this.displayName,
    required this.features,
  });

  final RuntimeMode mode;
  final String platformUrl;
  final String tenantUrl;
  final String userId;
  final String userType;
  final String displayName;
  final List<String> features;

  bool hasFeature(String feature) => features.contains(feature);

  /// The active base URL for API requests in this runtime.
  String get activeBaseUrl => RuntimeEndpoints.resolveBaseUrl(
        mode: mode,
        platformUrl: platformUrl,
        tenantUrl: tenantUrl,
      );

  @override
  String toString() => 'RuntimeContext(mode: $mode, user: $userId, type: $userType)';
}

// ─── Service ─────────────────────────────────────────────────────────────────

/// PHASE M3.2 — Determines which runtime mode the app should operate in.
///
/// Runtime is determined from the login response payload — never hardcoded.
///
/// Key fields in the login response that determine runtime:
///   - `runtime`: 'platform' | 'tenant'
///   - `user_type`: 'admin' | 'platform_staff' | 'customer_admin' | 'end_user' | 'support_agent'
///   - `platform_url`: Base URL of the saas-platform API
///   - `tenant_url`: Base URL of the customer-site API (null in PLATFORM_MODE)
///   - `features`: List<String> of enabled feature keys
class RuntimeSelectorService {
  RuntimeSelectorService({required SecureTokenService tokenService})
      : _tokenService = tokenService;

  final SecureTokenService _tokenService;

  static const _contextKey = 'runtime_context';

  /// Returns the current runtime mode from stored context.
  Future<RuntimeMode> currentMode() async {
    final ctx = await currentContext();
    return ctx.mode;
  }

  /// Returns the full runtime context. Throws if not authenticated.
  Future<RuntimeContext> currentContext() async {
    final stored = await _tokenService.read(SecureKey.runtimeContext);
    if (stored == null) {
      throw StateError('No runtime context stored. User must log in first.');
    }

    final Map<String, dynamic> data = jsonDecode(stored) as Map<String, dynamic>;
    return _buildContext(data);
  }

  /// Called after successful login to store the runtime context.
  /// [loginResponse] is the decoded JSON response from the login endpoint.
  Future<void> storeFromLoginResponse(Map<String, dynamic> loginResponse) async {
    final context = _buildContext(loginResponse);
    await _tokenService.write(
      SecureKey.runtimeContext,
      jsonEncode(_contextToMap(context)),
    );
  }

  /// Clears the runtime context on logout.
  Future<void> clear() async {
    await _tokenService.delete(SecureKey.runtimeContext);
  }

  /// Returns whether the given feature is enabled in the current runtime.
  Future<bool> hasFeature(String feature) async {
    final ctx = await currentContext();
    return ctx.hasFeature(feature);
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  RuntimeContext _buildContext(Map<String, dynamic> data) {
    final runtimeStr = (data['runtime'] as String? ?? 'tenant').toLowerCase();
    final userType   = (data['user_type'] as String? ?? 'end_user').toLowerCase();

    // Resolve mode: explicit 'runtime' field takes precedence.
    // Fallback: infer from user_type.
    final mode = _resolveMode(runtimeStr, userType);

    // Feature list: from server (authoritative) + mode defaults as fallback
    final serverFeatures = (data['features'] as List?)?.cast<String>() ?? [];
    final modeDefaults   = RuntimeFeatureSet.features[mode] ?? [];
    final features       = serverFeatures.isNotEmpty ? serverFeatures : modeDefaults;

    return RuntimeContext(
      mode:         mode,
      platformUrl:  (data['platform_url'] as String?) ?? '',
      tenantUrl:    (data['tenant_url'] as String?) ?? '',
      userId:       (data['user_id'] as Object?)?.toString() ?? '',
      userType:     userType,
      displayName:  (data['display_name'] as String?) ?? (data['name'] as String?) ?? '',
      features:     features,
    );
  }

  RuntimeMode _resolveMode(String runtimeStr, String userType) {
    // Explicit runtime field wins
    if (runtimeStr == 'platform') return RuntimeMode.platformMode;
    if (runtimeStr == 'tenant') return RuntimeMode.tenantMode;

    // Infer from user type
    const platformActors = {'admin', 'platform_staff', 'customer_admin'};
    return platformActors.contains(userType)
        ? RuntimeMode.platformMode
        : RuntimeMode.tenantMode;
  }

  Map<String, dynamic> _contextToMap(RuntimeContext ctx) => {
    'runtime':      ctx.mode == RuntimeMode.platformMode ? 'platform' : 'tenant',
    'platform_url': ctx.platformUrl,
    'tenant_url':   ctx.tenantUrl,
    'user_id':      ctx.userId,
    'user_type':    ctx.userType,
    'display_name': ctx.displayName,
    'features':     ctx.features,
  };
}
