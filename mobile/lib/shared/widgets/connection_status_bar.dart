import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/realtime/realtime_client_service.dart';
import '../providers/realtime_provider.dart';

class ConnectionStatusBar extends ConsumerStatefulWidget {
  const ConnectionStatusBar({super.key});

  @override
  ConsumerState<ConnectionStatusBar> createState() => _ConnectionStatusBarState();
}

class _ConnectionStatusBarState extends ConsumerState<ConnectionStatusBar> {
  // برای جلوگیری از نمایش فوری — اتصال اولیه چند ثانیه طول می‌کشد
  bool _visible = false;
  Timer? _showTimer;
  RealtimeConnectionState _lastState = RealtimeConnectionState.disconnected;

  @override
  void dispose() {
    _showTimer?.cancel();
    super.dispose();
  }

  void _onStateChange(RealtimeConnectionState state) {
    if (state == RealtimeConnectionState.connected) {
      _showTimer?.cancel();
      if (mounted) setState(() => _visible = false);
      return;
    }

    if (state != _lastState) {
      _lastState = state;
      _showTimer?.cancel();
      // نمایش بعد از ۸ ثانیه اگر هنوز وصل نشده
      _showTimer = Timer(const Duration(seconds: 8), () {
        if (mounted && _lastState != RealtimeConnectionState.connected) {
          setState(() => _visible = true);
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<RealtimeConnectionState>(
      stream: ref.watch(realtimeServiceProvider).connectionState,
      builder: (context, snap) {
        final state = snap.data ?? RealtimeConnectionState.disconnected;
        _onStateChange(state);

        if (!_visible || state == RealtimeConnectionState.connected) {
          return const SizedBox.shrink();
        }

        final (color, label, showSpinner) = switch (state) {
          RealtimeConnectionState.connecting   => (Colors.amber.shade700,  'در حال اتصال به سرور...', true),
          RealtimeConnectionState.reconnecting => (Colors.orange.shade700, 'در حال اتصال مجدد...', true),
          RealtimeConnectionState.failed       => (Colors.red.shade700,    'اتصال بلادرنگ قطع است', false),
          _                                    => (Colors.grey.shade600,   'حالت آفلاین', false),
        };

        return AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 5, horizontal: 16),
          color: color.withValues(alpha: 0.85),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (showSpinner) ...[
                SizedBox(
                  width: 11,
                  height: 11,
                  child: CircularProgressIndicator(strokeWidth: 1.5, color: Colors.white.withValues(alpha: 0.9)),
                ),
                const SizedBox(width: 8),
              ] else ...[
                Icon(Icons.wifi_off_rounded, size: 13, color: Colors.white.withValues(alpha: 0.9)),
                const SizedBox(width: 6),
              ],
              Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w500)),
            ],
          ),
        );
      },
    );
  }
}
