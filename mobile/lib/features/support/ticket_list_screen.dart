import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/client_api_service.dart';

// ─── State ────────────────────────────────────────────────────────────────────

class TicketListState {
  const TicketListState({
    this.tickets = const [],
    this.isLoading = false,
    this.error,
    this.statusFilter,
    this.priorityFilter,
    this.assignedFilter,
    this.currentPage = 1,
    this.lastPage = 1,
    this.total = 0,
  });

  final List<TicketSummary> tickets;
  final bool isLoading;
  final String? error;
  final String? statusFilter;
  final String? priorityFilter;
  final String? assignedFilter;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  TicketListState copyWith({
    List<TicketSummary>? tickets,
    bool? isLoading,
    String? error,
    String? statusFilter,
    String? priorityFilter,
    String? assignedFilter,
    int? currentPage,
    int? lastPage,
    int? total,
  }) =>
      TicketListState(
        tickets:        tickets        ?? this.tickets,
        isLoading:      isLoading      ?? this.isLoading,
        error:          error,
        statusFilter:   statusFilter   ?? this.statusFilter,
        priorityFilter: priorityFilter ?? this.priorityFilter,
        assignedFilter: assignedFilter ?? this.assignedFilter,
        currentPage:    currentPage    ?? this.currentPage,
        lastPage:       lastPage       ?? this.lastPage,
        total:          total          ?? this.total,
      );
}

// ─── Notifier ─────────────────────────────────────────────────────────────────

class TicketListNotifier extends StateNotifier<TicketListState> {
  TicketListNotifier(this._ref) : super(const TicketListState()) {
    load();
  }

  final Ref _ref;

  Future<void> load({bool refresh = false}) async {
    // فقط اگر در حال بارگذاری است و درخواست refresh نشده، skip کن
    if (state.isLoading && !refresh) return;

    state = state.copyWith(isLoading: true, error: null);

    try {
      final api  = _ref.read(clientApiServiceProvider);
      final page = await api.getTickets(
        status:   state.statusFilter,
        priority: state.priorityFilter,
        assigned: state.assignedFilter,
      );

      state = state.copyWith(
        tickets:     page.tickets,
        currentPage: page.currentPage,
        lastPage:    page.lastPage,
        total:       page.total,
        isLoading:   false,
      );
    } catch (e) {
      final msg = e.toString();
      final farsi = msg.contains('404')  ? 'سرویس تیکت‌ها در دسترس نیست.'
                  : msg.contains('401')  ? 'لطفاً دوباره وارد شوید.'
                  : msg.contains('500')  ? 'خطای سرور. لطفاً چند لحظه بعد امتحان کنید.'
                  : msg.contains('SocketException') || msg.contains('connection') ? 'اتصال به سرور برقرار نشد.'
                  : 'خطا در بارگذاری تیکت‌ها.';
      state = state.copyWith(isLoading: false, error: farsi);
    }
  }

  void setFilter({String? status, String? priority, String? assigned}) {
    state = TicketListState(
      statusFilter:   status,
      priorityFilter: priority,
      assignedFilter: assigned,
      isLoading:      true,
    );
    load(refresh: true);
  }

  void clearFilters() => setFilter();
}

final ticketListProvider = StateNotifierProvider.autoDispose<TicketListNotifier, TicketListState>(
  (ref) => TicketListNotifier(ref),
);

// ─── Screen ───────────────────────────────────────────────────────────────────

class TicketListScreen extends ConsumerWidget {
  const TicketListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(ticketListProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text('تیکت‌ها ${state.total > 0 ? "(${state.total})" : ""}'),
        actions: [
          if (state.statusFilter != null || state.priorityFilter != null || state.assignedFilter != null)
            IconButton(
              icon: const Icon(Icons.filter_alt_off),
              tooltip: 'حذف فیلتر',
              onPressed: () => ref.read(ticketListProvider.notifier).clearFilters(),
            ),
          IconButton(
            icon: const Icon(Icons.filter_list),
            tooltip: 'فیلتر',
            onPressed: () => _showFilterSheet(context, ref, state),
          ),
          IconButton(
            icon: const Icon(Icons.add),
            tooltip: 'تیکت جدید',
            onPressed: () => context.push('/tickets/create'),
          ),
        ],
      ),
      body: state.isLoading
          ? const Center(child: CircularProgressIndicator())
          : state.error != null
              ? _ErrorView(
                  error: state.error!,
                  onRetry: () => ref.read(ticketListProvider.notifier).load(refresh: true),
                )
              : state.tickets.isEmpty
                  ? _EmptyView(
                      hasFilters: state.statusFilter != null || state.priorityFilter != null,
                      onClearFilters: () => ref.read(ticketListProvider.notifier).clearFilters(),
                      onCreate: () => context.push('/tickets/create'),
                    )
                  : RefreshIndicator(
                      onRefresh: () => ref.read(ticketListProvider.notifier).load(refresh: true),
                      child: ListView.separated(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        itemCount: state.tickets.length,
                        separatorBuilder: (_, __) => const Divider(height: 1, indent: 16, endIndent: 16),
                        itemBuilder: (context, index) {
                          final ticket = state.tickets[index];
                          return _TicketTile(
                            ticket: ticket,
                            onTap: () => context.push('/tickets/${ticket.id}'),
                          );
                        },
                      ),
                    ),
    );
  }

  void _showFilterSheet(BuildContext context, WidgetRef ref, TicketListState state) {
    showModalBottomSheet(
      context: context,
      builder: (_) => _FilterSheet(
        currentStatus:   state.statusFilter,
        currentPriority: state.priorityFilter,
        currentAssigned: state.assignedFilter,
        onApply: (status, priority, assigned) {
          ref.read(ticketListProvider.notifier).setFilter(
            status:   status,
            priority: priority,
            assigned: assigned,
          );
        },
      ),
    );
  }
}

// ─── Ticket tile ─────────────────────────────────────────────────────────────

class _TicketTile extends StatelessWidget {
  const _TicketTile({required this.ticket, required this.onTap});

  final TicketSummary ticket;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      leading: _PriorityIndicator(priority: ticket.priority),
      title: Text(
        ticket.subject,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w500),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 2),
          Text(
            '${ticket.ticketNumber} • ${ticket.customerName ?? ticket.customerMobile ?? "—"}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 4),
          Row(children: [
            _StatusChip(status: ticket.status),
            if (ticket.category != null) ...[
              const SizedBox(width: 6),
              _CategoryChip(category: ticket.category!),
            ],
          ]),
        ],
      ),
      isThreeLine: true,
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            _timeAgo(ticket.updatedAt),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey),
          ),
          if (ticket.assigneeName != null)
            Text(
              ticket.assigneeName!,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.blue),
            ),
        ],
      ),
    );
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60)  return '${diff.inMinutes}m';
    if (diff.inHours < 24)    return '${diff.inHours}h';
    if (diff.inDays < 7)      return '${diff.inDays}d';
    return '${(diff.inDays / 7).floor()}w';
  }
}

// ─── Priority indicator ───────────────────────────────────────────────────────

class _PriorityIndicator extends StatelessWidget {
  const _PriorityIndicator({required this.priority});
  final String priority;

  @override
  Widget build(BuildContext context) {
    final color = switch (priority) {
      'high'   => Colors.red,
      'normal' => Colors.orange,
      _        => Colors.grey,
    };
    return Container(
      width: 4,
      height: 48,
      margin: const EdgeInsets.only(right: 4),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2)),
    );
  }
}

// ─── Status chip ──────────────────────────────────────────────────────────────

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'open'        => ('باز', Colors.blue),
      'in_progress' => ('در حال بررسی', Colors.orange),
      'closed'      => ('بسته', Colors.grey),
      _             => (status, Colors.grey),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(label, style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w600)),
    );
  }
}

// ─── Category chip ────────────────────────────────────────────────────────────

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({required this.category});
  final String category;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: Colors.purple.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(category, style: const TextStyle(fontSize: 10, color: Colors.purple)),
    );
  }
}

// ─── Filter sheet ─────────────────────────────────────────────────────────────

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    this.currentStatus,
    this.currentPriority,
    this.currentAssigned,
    required this.onApply,
  });

  final String? currentStatus;
  final String? currentPriority;
  final String? currentAssigned;
  final void Function(String? status, String? priority, String? assigned) onApply;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  String? _status;
  String? _priority;
  String? _assigned;

  @override
  void initState() {
    super.initState();
    _status   = widget.currentStatus;
    _priority = widget.currentPriority;
    _assigned = widget.currentAssigned;
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('فیلتر تیکت‌ها', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
            _buildChipRow('وضعیت', [
              ('باز', 'open'),
              ('در حال بررسی', 'in_progress'),
              ('بسته', 'closed'),
            ], _status, (v) => setState(() => _status = v)),
            const SizedBox(height: 12),
            _buildChipRow('اولویت', [
              ('بالا', 'high'),
              ('عادی', 'normal'),
              ('پایین', 'low'),
            ], _priority, (v) => setState(() => _priority = v)),
            const SizedBox(height: 12),
            _buildChipRow('تخصیص', [
              ('تیکت‌های من', 'me'),
            ], _assigned, (v) => setState(() => _assigned = v)),
            const SizedBox(height: 20),
            Row(children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    setState(() { _status = _priority = _assigned = null; });
                  },
                  child: const Text('پاک کردن'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  onPressed: () {
                    Navigator.pop(context);
                    widget.onApply(_status, _priority, _assigned);
                  },
                  child: const Text('اعمال'),
                ),
              ),
            ]),
          ],
        ),
      ),
    );
  }

  Widget _buildChipRow(
    String label,
    List<(String, String)> options,
    String? selected,
    void Function(String?) onSelect,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        Wrap(
          spacing: 8,
          children: options.map((opt) {
            final (l, v) = opt;
            final isSelected = selected == v;
            return FilterChip(
              label: Text(l),
              selected: isSelected,
              onSelected: (_) => onSelect(isSelected ? null : v),
            );
          }).toList(),
        ),
      ],
    );
  }
}

// ─── Empty/Error views ────────────────────────────────────────────────────────

class _EmptyView extends StatelessWidget {
  const _EmptyView({required this.hasFilters, required this.onClearFilters, required this.onCreate});
  final bool hasFilters;
  final VoidCallback onClearFilters;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.support_agent, size: 64, color: Colors.grey.shade400),
            const SizedBox(height: 16),
            Text(
              hasFilters ? 'تیکتی با این فیلتر یافت نشد' : 'هیچ تیکتی وجود ندارد',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 24),
            if (hasFilters)
              OutlinedButton.icon(
                onPressed: onClearFilters,
                icon: const Icon(Icons.filter_alt_off),
                label: const Text('حذف فیلتر'),
              )
            else
              FilledButton.icon(
                onPressed: onCreate,
                icon: const Icon(Icons.add),
                label: const Text('تیکت جدید'),
              ),
          ],
        ),
      ),
    );
  }
}

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
          const SizedBox(height: 12),
          Text('خطا در بارگذاری', style: Theme.of(context).textTheme.titleMedium),
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
