import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/client_api_service.dart';
import '../../core/realtime/realtime_client_service.dart';
import '../../core/realtime/realtime_event.dart';

// ─── Dashboard state ──────────────────────────────────────────────────────────

class DashboardStats {
  const DashboardStats({
    this.openConversations    = 0,
    this.assignedConversations = 0,
    this.unresolvedConversations = 0,
    this.onlineCustomers      = 0,
    this.slaWarnings          = 0,
    this.unreadMessages       = 0,
    this.loading              = false,
    this.error,
    this.lastUpdated,
  });

  final int openConversations;
  final int assignedConversations;
  final int unresolvedConversations;
  final int onlineCustomers;
  final int slaWarnings;
  final int unreadMessages;
  final bool loading;
  final String? error;
  final DateTime? lastUpdated;

  DashboardStats copyWith({
    int? openConversations,
    int? assignedConversations,
    int? unresolvedConversations,
    int? onlineCustomers,
    int? slaWarnings,
    int? unreadMessages,
    bool? loading,
    String? error,
    DateTime? lastUpdated,
  }) =>
      DashboardStats(
        openConversations:     openConversations     ?? this.openConversations,
        assignedConversations: assignedConversations ?? this.assignedConversations,
        unresolvedConversations: unresolvedConversations ?? this.unresolvedConversations,
        onlineCustomers:       onlineCustomers       ?? this.onlineCustomers,
        slaWarnings:           slaWarnings           ?? this.slaWarnings,
        unreadMessages:        unreadMessages        ?? this.unreadMessages,
        loading:               loading               ?? this.loading,
        error:                 error,
        lastUpdated:           lastUpdated           ?? this.lastUpdated,
      );
}

// ─── Dashboard notifier ───────────────────────────────────────────────────────

class DashboardNotifier extends StateNotifier<DashboardStats> {
  DashboardNotifier(this._ref) : super(const DashboardStats(loading: true)) {
    _init();
  }

  final Ref _ref;
  StreamSubscription<RealtimeEvent>? _realtimeSub;
  Timer? _pollTimer;

  Future<void> _init() async {
    await refresh();
    _bindRealtime();
    // Fallback poll every 60s when WebSocket is unavailable
    _pollTimer = Timer.periodic(const Duration(seconds: 60), (_) => refresh());
  }

  Future<void> refresh() async {
    try {
      final api  = _ref.read(clientApiServiceProvider);
      final data = await api.getDashboardStats();

      state = state.copyWith(
        openConversations:     data['open_conversations']     as int? ?? 0,
        assignedConversations: data['assigned_conversations'] as int? ?? 0,
        unresolvedConversations: data['unresolved_conversations'] as int? ?? 0,
        onlineCustomers:       data['online_customers']       as int? ?? 0,
        slaWarnings:           data['sla_warnings']           as int? ?? 0,
        unreadMessages:        data['unread_messages']        as int? ?? 0,
        loading:               false,
        lastUpdated:           DateTime.now(),
      );
    } catch (e) {
      state = state.copyWith(loading: false, error: e.toString());
    }
  }

  void _bindRealtime() {
    try {
      final rt = _ref.read(realtimeClientServiceProvider);
      _realtimeSub = rt.events.listen((event) {
        if (_isDashboardEvent(event)) refresh();
      });
    } catch (_) {}
  }

  bool _isDashboardEvent(RealtimeEvent event) => const {
    'conversation.created',
    'conversation.closed',
    'conversation.assignment.changed',
    'message.sent',
    'presence.updated',
  }.contains(event.eventName);

  @override
  void dispose() {
    _realtimeSub?.cancel();
    _pollTimer?.cancel();
    super.dispose();
  }
}

final dashboardProvider =
    StateNotifierProvider<DashboardNotifier, DashboardStats>((ref) {
  return DashboardNotifier(ref);
});

// ─── Screen ───────────────────────────────────────────────────────────────────

class SupportDashboardScreen extends ConsumerWidget {
  const SupportDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(dashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('داشبورد پشتیبانی'),
        actions: [
          if (stats.loading)
            const Padding(
              padding: EdgeInsets.all(16),
              child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: () => ref.read(dashboardProvider.notifier).refresh(),
            ),
        ],
      ),
      body: stats.error != null
          ? _ErrorView(error: stats.error!, onRetry: () => ref.read(dashboardProvider.notifier).refresh())
          : RefreshIndicator(
              onRefresh: () => ref.read(dashboardProvider.notifier).refresh(),
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (stats.lastUpdated != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          'بروزرسانی: ${_formatTime(stats.lastUpdated!)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    _DashboardGrid(stats: stats),
                    const SizedBox(height: 24),
                    if (stats.slaWarnings > 0) ...[
                      _SlaWarningBanner(count: stats.slaWarnings),
                      const SizedBox(height: 16),
                    ],
                  ],
                ),
              ),
            ),
    );
  }

  String _formatTime(DateTime t) {
    final diff = DateTime.now().difference(t);
    if (diff.inSeconds < 60)  return 'همین الان';
    if (diff.inMinutes < 60)  return '${diff.inMinutes} دقیقه پیش';
    return '${diff.inHours} ساعت پیش';
  }
}

// ─── Dashboard grid ───────────────────────────────────────────────────────────

class _DashboardGrid extends StatelessWidget {
  const _DashboardGrid({required this.stats});
  final DashboardStats stats;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount:   MediaQuery.of(context).size.width > 600 ? 3 : 2,
      shrinkWrap:       true,
      physics:          const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 12,
      mainAxisSpacing:  12,
      childAspectRatio: 1.4,
      children: [
        _StatWidget(label: 'باز',           value: stats.openConversations,     icon: Icons.chat_bubble_outline, color: Colors.blue,   urgent: false),
        _StatWidget(label: 'تخصیص یافته',   value: stats.assignedConversations,  icon: Icons.assignment_ind,      color: Colors.green,  urgent: false),
        _StatWidget(label: 'بی‌پاسخ',       value: stats.unresolvedConversations,icon: Icons.pending_actions,     color: Colors.orange, urgent: stats.unresolvedConversations > 10),
        _StatWidget(label: 'آنلاین',        value: stats.onlineCustomers,        icon: Icons.circle,              color: Colors.teal,   urgent: false),
        _StatWidget(label: 'هشدار SLA',     value: stats.slaWarnings,            icon: Icons.warning_amber,       color: stats.slaWarnings > 0 ? Colors.red : Colors.grey, urgent: stats.slaWarnings > 0),
        _StatWidget(label: 'خوانده‌نشده',   value: stats.unreadMessages,         icon: Icons.mark_chat_unread,    color: Colors.purple, urgent: stats.unreadMessages > 0),
      ],
    );
  }
}

// ─── Stat widget ─────────────────────────────────────────────────────────────

class _StatWidget extends StatelessWidget {
  const _StatWidget({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.urgent,
  });

  final String  label;
  final int     value;
  final IconData icon;
  final Color   color;
  final bool    urgent;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: urgent ? 4 : 1,
      color:     urgent ? color.withValues(alpha: 0.08) : null,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: urgent
            ? BorderSide(color: color, width: 1.5)
            : BorderSide.none,
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment:  MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Icon(icon, color: color, size: 22),
                if (urgent)
                  Container(
                    width: 8, height: 8,
                    decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                  ),
              ],
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$value',
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color:      urgent ? color : null,
                  ),
                ),
                Text(label, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ─── SLA warning banner ───────────────────────────────────────────────────────

class _SlaWarningBanner extends StatelessWidget {
  const _SlaWarningBanner({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.red.shade200),
      ),
      child: Row(
        children: [
          Icon(Icons.warning, color: Colors.red.shade700),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '$count مکالمه در آستانه نقض SLA هستند — اقدام فوری لازم است.',
              style: TextStyle(color: Colors.red.shade700, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Error view ───────────────────────────────────────────────────────────────

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.error, required this.onRetry});
  final String error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.red),
          const SizedBox(height: 16),
          Text('خطا در بارگذاری داشبورد', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Text(error, style: Theme.of(context).textTheme.bodySmall, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('تلاش مجدد'),
          ),
        ],
      ),
    );
  }
}
