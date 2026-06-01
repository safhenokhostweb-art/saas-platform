import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final conversationDeepLinkServiceProvider = Provider<ConversationDeepLinkService>((ref) {
  final service = ConversationDeepLinkService();
  ref.onDispose(service.dispose);
  return service;
});

// ─── Deep link targets ────────────────────────────────────────────────────────

sealed class DeepLinkTarget {
  const DeepLinkTarget();
}

class ConversationDeepLink extends DeepLinkTarget {
  const ConversationDeepLink(this.conversationId);
  final String conversationId;
}

class TenantDeepLink extends DeepLinkTarget {
  const TenantDeepLink(this.tenantId);
  final String tenantId;
}

class UnknownDeepLink extends DeepLinkTarget {
  const UnknownDeepLink(this.rawUri);
  final String rawUri;
}

// ─── Service ─────────────────────────────────────────────────────────────────

/// Handles `support://conversation/{id}` and `support://tenant/{id}` schemes.
///
/// Works on Android (intent filter) and Windows (protocol handler).
/// Notification clicks should call [handleUri] with the target URI.
class ConversationDeepLinkService {
  ConversationDeepLinkService();

  final _controller = StreamController<DeepLinkTarget>.broadcast();

  Stream<DeepLinkTarget> get onDeepLink => _controller.stream;

  StreamSubscription<Uri>? _linkSub;
  final _appLinks = AppLinks();

  // ── Initialize (call once on app start) ──────────────────────────────────

  Future<void> initialize() async {
    // Handle cold-start link
    try {
      final initial = await _appLinks.getInitialLink();
      if (initial != null) _dispatch(initial);
    } catch (_) {
      // No initial link
    }

    // Handle foreground links
    _linkSub = _appLinks.uriLinkStream.listen(
      _dispatch,
      onError: (_) {},
    );
  }

  // ── Handle a URI directly (e.g., from notification tap) ──────────────────

  void handleUri(String uriString) {
    final uri = Uri.tryParse(uriString);
    if (uri != null) _dispatch(uri);
  }

  // ── Build deep link URIs (for sharing / QR codes) ────────────────────────

  static String conversationUri(String conversationId) =>
      'support://conversation/$conversationId';

  static String tenantUri(String tenantId) =>
      'support://tenant/$tenantId';

  // ── Parse a URI string to a target ───────────────────────────────────────

  static DeepLinkTarget parse(String uriString) {
    final uri = Uri.tryParse(uriString);
    if (uri == null) return UnknownDeepLink(uriString);
    return _parseUri(uri);
  }

  void dispose() {
    _linkSub?.cancel();
    _controller.close();
  }

  // ── Private ───────────────────────────────────────────────────────────────

  void _dispatch(Uri uri) {
    _controller.add(_parseUri(uri));
  }

  static DeepLinkTarget _parseUri(Uri uri) {
    if (uri.scheme != 'support') return UnknownDeepLink(uri.toString());

    return switch (uri.host) {
      'conversation' => ConversationDeepLink(
          uri.pathSegments.isNotEmpty ? uri.pathSegments.first : '',
        ),
      'tenant' => TenantDeepLink(
          uri.pathSegments.isNotEmpty ? uri.pathSegments.first : '',
        ),
      _ => UnknownDeepLink(uri.toString()),
    };
  }
}
