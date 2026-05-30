import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/realtime/realtime_client_service.dart';
import '../providers/realtime_provider.dart';

class ConnectionStatusBar extends ConsumerWidget {
  const ConnectionStatusBar({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return StreamBuilder<RealtimeConnectionState>(
      stream: ref.watch(realtimeServiceProvider).connectionState,
      builder: (context, snap) {
        final state = snap.data ?? RealtimeConnectionState.disconnected;
        if (state == RealtimeConnectionState.connected) return const SizedBox.shrink();

        final (color, label) = switch (state) {
          RealtimeConnectionState.connecting => (Colors.amber.shade700, 'Connecting…'),
          RealtimeConnectionState.reconnecting => (Colors.orange.shade700, 'Reconnecting…'),
          RealtimeConnectionState.failed => (Colors.red.shade700, 'Connection failed'),
          _ => (Colors.grey.shade700, 'Offline'),
        };

        return Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 16),
          color: color,
          child: Row(
            children: [
              if (state != RealtimeConnectionState.failed)
                const SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 1.5, color: Colors.white)),
              if (state != RealtimeConnectionState.failed) const SizedBox(width: 8),
              Text(label, style: const TextStyle(color: Colors.white, fontSize: 12)),
            ],
          ),
        );
      },
    );
  }
}
