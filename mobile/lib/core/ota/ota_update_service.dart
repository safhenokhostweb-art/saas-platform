import 'dart:async';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../api/client_api_service.dart';
import '../api/models/ota_models.dart';
import '../security/secure_token_service.dart';

// ─── Providers ────────────────────────────────────────────────────────────────

final otaUpdateServiceProvider = StateNotifierProvider<OtaUpdateService, OtaState>((ref) {
  return OtaUpdateService(
    api:    ref.watch(clientApiServiceProvider),
    tokens: ref.watch(secureTokenServiceProvider),
  );
});

// ─── Update channel ────────────────────────────────────────────────────────────

enum UpdateChannel { stable, beta, canary }

extension UpdateChannelName on UpdateChannel {
  String get name => switch (this) {
    UpdateChannel.stable => 'stable',
    UpdateChannel.beta   => 'beta',
    UpdateChannel.canary => 'canary',
  };

  static UpdateChannel fromString(String s) => switch (s) {
    'beta'   => UpdateChannel.beta,
    'canary' => UpdateChannel.canary,
    _        => UpdateChannel.stable,
  };
}

// ─── State ────────────────────────────────────────────────────────────────────

class OtaState {
  const OtaState({
    this.manifest,
    this.checking   = false,
    this.channel    = UpdateChannel.stable,
    this.error,
    this.lastChecked,
  });

  final OtaManifest?  manifest;
  final bool          checking;
  final UpdateChannel channel;
  final String?       error;
  final DateTime?     lastChecked;

  bool get updateAvailable => manifest?.updateAvailable ?? false;
  bool get mandatory       => manifest?.mandatory       ?? false;
  String? get downloadUrl  => manifest?.downloadUrl;
  String? get version      => manifest?.latestVersion;
  String? get checksum     => manifest?.checksum;

  OtaState copyWith({
    OtaManifest? manifest,
    bool?        checking,
    UpdateChannel? channel,
    String?      error,
    DateTime?    lastChecked,
  }) =>
      OtaState(
        manifest:    manifest    ?? this.manifest,
        checking:    checking    ?? this.checking,
        channel:     channel     ?? this.channel,
        error:       error,
        lastChecked: lastChecked ?? this.lastChecked,
      );
}

// ─── Service ─────────────────────────────────────────────────────────────────

class OtaUpdateService extends StateNotifier<OtaState> {
  OtaUpdateService({required ClientApiService api, required SecureTokenService tokens})
      : _api    = api,
        _tokens = tokens,
        super(const OtaState()) {
    _loadChannel();
    _schedulePeriodicCheck();
  }

  final ClientApiService  _api;
  final SecureTokenService _tokens;
  Timer? _checkTimer;

  // ── Channel management ────────────────────────────────────────────────────

  Future<void> _loadChannel() async {
    final raw = await _tokens.read(SecureKey.updateChannel) ?? 'stable';
    state = state.copyWith(channel: UpdateChannelName.fromString(raw));
  }

  Future<void> setChannel(UpdateChannel channel) async {
    await _tokens.write(SecureKey.updateChannel, channel.name);
    state = state.copyWith(channel: channel);
    await checkForUpdates();
  }

  // ── Check for updates ─────────────────────────────────────────────────────

  Future<OtaManifest?> checkForUpdates() async {
    state = state.copyWith(checking: true, error: null);
    try {
      final info     = await PackageInfo.fromPlatform();
      final manifest = await _api.getOtaManifest(info.version, state.channel.name);
      state = state.copyWith(
        manifest:    manifest,
        checking:    false,
        lastChecked: DateTime.now(),
      );
      return manifest;
    } catch (e) {
      state = state.copyWith(checking: false, error: e.toString());
      return null;
    }
  }

  // ── Periodic check (every 6 hours) ───────────────────────────────────────

  void _schedulePeriodicCheck() {
    _checkTimer = Timer.periodic(const Duration(hours: 6), (_) {
      checkForUpdates();
    });
    // Initial check after 10 seconds to not block startup
    Timer(const Duration(seconds: 10), checkForUpdates);
  }

  // ── Platform-specific update runtimes ─────────────────────────────────────

  bool get isWindows => Platform.isWindows;
  bool get isAndroid => Platform.isAndroid;

  @override
  void dispose() {
    _checkTimer?.cancel();
    super.dispose();
  }
}
