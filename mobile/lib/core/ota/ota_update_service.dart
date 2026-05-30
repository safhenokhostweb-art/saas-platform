import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/client_api_service.dart';
import '../api/models/ota_models.dart';
import '../security/secure_token_service.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final otaUpdateServiceProvider = Provider<OtaUpdateService>((ref) {
  final api = ref.watch(clientApiServiceProvider);
  final tokens = ref.watch(secureTokenServiceProvider);
  return OtaUpdateService(api: api, tokens: tokens);
});

// ─── State ───────────────────────────────────────────────────────────────────

class OtaState {
  const OtaState({this.manifest, this.checking = false, this.error});
  final OtaManifest? manifest;
  final bool checking;
  final String? error;

  bool get updateAvailable => manifest?.updateAvailable ?? false;
  bool get mandatory => manifest?.mandatory ?? false;
}

// ─── Service ─────────────────────────────────────────────────────────────────

class OtaUpdateService extends StateNotifier<OtaState> {
  OtaUpdateService({required ClientApiService api, required SecureTokenService tokens})
      : _api = api,
        _tokens = tokens,
        super(const OtaState());

  final ClientApiService _api;
  final SecureTokenService _tokens;

  // Channels: stable | beta | canary (matches MobileUpdateChannelManager on server)
  Future<OtaManifest?> checkForUpdates() async {
    state = const OtaState(checking: true);
    try {
      final info = await PackageInfo.fromPlatform();
      final channel = await _tokens.read(SecureKey.updateChannel) ?? 'stable';
      final manifest = await _api.getOtaManifest(info.version, channel);
      state = OtaState(manifest: manifest);
      return manifest;
    } catch (e) {
      state = OtaState(error: e.toString());
      return null;
    }
  }

  Future<void> openUpdateUrl() async {
    final url = state.manifest?.downloadUrl;
    if (url == null) return;
    final uri = Uri.tryParse(url);
    if (uri != null && await canLaunchUrl(uri)) await launchUrl(uri);
  }

  String get currentChannel => 'stable';
}
