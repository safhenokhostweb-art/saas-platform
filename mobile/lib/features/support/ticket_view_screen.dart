import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/client_api_service.dart';

// ─── Providers ────────────────────────────────────────────────────────────────

final ticketDetailProvider = FutureProvider.autoDispose.family<TicketDetail, int>((ref, id) async {
  return ref.read(clientApiServiceProvider).getTicket(id);
});

// ─── Screen ───────────────────────────────────────────────────────────────────

class TicketViewScreen extends ConsumerStatefulWidget {
  const TicketViewScreen({super.key, required this.ticketId});
  final int ticketId;

  @override
  ConsumerState<TicketViewScreen> createState() => _TicketViewScreenState();
}

class _TicketViewScreenState extends ConsumerState<TicketViewScreen> {
  final _replyCtrl     = TextEditingController();
  final _scrollCtrl    = ScrollController();
  bool _isInternal     = false;
  bool _isSending      = false;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    // Poll for new replies every 30s (fallback when WebSocket unavailable)
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      ref.invalidate(ticketDetailProvider(widget.ticketId));
    });
  }

  @override
  void dispose() {
    _replyCtrl.dispose();
    _scrollCtrl.dispose();
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _sendReply() async {
    final text = _replyCtrl.text.trim();
    if (text.isEmpty) return;

    setState(() => _isSending = true);

    try {
      await ref.read(clientApiServiceProvider).replyToTicket(
        widget.ticketId,
        text,
        isInternal: _isInternal,
      );
      _replyCtrl.clear();
      ref.invalidate(ticketDetailProvider(widget.ticketId));

      // Scroll to bottom after refresh
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollCtrl.hasClients) {
          _scrollCtrl.animateTo(
            _scrollCtrl.position.maxScrollExtent,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          );
        }
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('خطا: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _changeStatus(TicketDetail ticket, String newStatus) async {
    try {
      await ref.read(clientApiServiceProvider).updateTicketStatus(ticket.id, newStatus);
      ref.invalidate(ticketDetailProvider(widget.ticketId));
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('وضعیت تیکت بروز شد.'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('خطا: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(ticketDetailProvider(widget.ticketId));

    return async.when(
      loading: () => Scaffold(appBar: AppBar(), body: const Center(child: CircularProgressIndicator())),
      error:   (e, _) => Scaffold(
        appBar: AppBar(title: const Text('خطا')),
        body: Center(child: Text('$e')),
      ),
      data: (ticket) => _buildContent(ticket),
    );
  }

  Widget _buildContent(TicketDetail ticket) {
    return Scaffold(
      appBar: AppBar(
        title: Text(ticket.ticketNumber, style: const TextStyle(fontSize: 16)),
        actions: [
          PopupMenuButton<String>(
            tooltip: 'تغییر وضعیت',
            onSelected: (s) => _changeStatus(ticket, s),
            itemBuilder: (_) => [
              if (!ticket.isOpen)
                const PopupMenuItem(value: 'open', child: Text('بازگشایی')),
              if (!ticket.isInProgress)
                const PopupMenuItem(value: 'in_progress', child: Text('در حال بررسی')),
              if (!ticket.isClosed)
                const PopupMenuItem(value: 'closed', child: Text('بستن تیکت')),
            ],
          ),
        ],
      ),
      body: Column(
        children: [
          // ── Header ──────────────────────────────────────────────────
          _TicketHeader(ticket: ticket),

          // ── Replies list ─────────────────────────────────────────────
          Expanded(
            child: ticket.replies.isEmpty
                ? const Center(
                    child: Text('هنوز پاسخی ثبت نشده', style: TextStyle(color: Colors.grey)),
                  )
                : ListView.builder(
                    controller: _scrollCtrl,
                    padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                    itemCount: ticket.replies.length,
                    itemBuilder: (_, i) => _ReplyBubble(reply: ticket.replies[i]),
                  ),
          ),

          // ── Reply box ────────────────────────────────────────────────
          if (!ticket.isClosed) _ReplyBox(
            controller: _replyCtrl,
            isSending: _isSending,
            isInternal: _isInternal,
            onToggleInternal: () => setState(() => _isInternal = !_isInternal),
            onSend: _sendReply,
          ),

          if (ticket.isClosed)
            Container(
              padding: const EdgeInsets.all(12),
              color: Colors.grey.shade100,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.lock, size: 16, color: Colors.grey),
                  const SizedBox(width: 6),
                  const Text('این تیکت بسته است', style: TextStyle(color: Colors.grey)),
                  const SizedBox(width: 12),
                  TextButton(
                    onPressed: () => _changeStatus(ticket, 'open'),
                    child: const Text('بازگشایی'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

// ─── Ticket header ────────────────────────────────────────────────────────────

class _TicketHeader extends StatelessWidget {
  const _TicketHeader({required this.ticket});
  final TicketDetail ticket;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        border: Border(bottom: BorderSide(color: Colors.grey.shade300)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(ticket.subject, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Wrap(spacing: 8, runSpacing: 4, children: [
            _InfoChip(icon: Icons.circle, label: ticket.statusLabel, color: _statusColor(ticket.status)),
            _InfoChip(icon: Icons.flag, label: ticket.priorityLabel, color: _priorityColor(ticket.priority)),
            if (ticket.category != null)
              _InfoChip(icon: Icons.category, label: ticket.category!, color: Colors.purple),
          ]),
          const SizedBox(height: 8),
          Row(children: [
            const Icon(Icons.person, size: 14, color: Colors.grey),
            const SizedBox(width: 4),
            Text(
              ticket.customerName ?? ticket.customerMobile ?? '—',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (ticket.assigneeName != null) ...[
              const SizedBox(width: 16),
              const Icon(Icons.assignment_ind, size: 14, color: Colors.grey),
              const SizedBox(width: 4),
              Text(ticket.assigneeName!, style: Theme.of(context).textTheme.bodySmall),
            ],
          ]),
          if (ticket.description.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.blue.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(ticket.description, style: Theme.of(context).textTheme.bodyMedium),
            ),
          ],
        ],
      ),
    );
  }

  String get statusLabel => switch (ticket.status) {
    'open'        => 'باز',
    'in_progress' => 'در حال بررسی',
    'closed'      => 'بسته',
    _             => ticket.status,
  };

  String get priorityLabel => switch (ticket.priority) {
    'low'    => 'پایین',
    'normal' => 'عادی',
    'high'   => 'بالا',
    _        => ticket.priority,
  };

  Color _statusColor(String s) => switch (s) {
    'open'        => Colors.blue,
    'in_progress' => Colors.orange,
    'closed'      => Colors.grey,
    _             => Colors.grey,
  };

  Color _priorityColor(String p) => switch (p) {
    'high'   => Colors.red,
    'normal' => Colors.orange,
    _        => Colors.grey,
  };
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label, required this.color});
  final IconData icon;
  final String   label;
  final Color    color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 10, color: color),
        const SizedBox(width: 4),
        Text(label, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
      ]),
    );
  }
}

// ─── Reply bubble ─────────────────────────────────────────────────────────────

class _ReplyBubble extends StatelessWidget {
  const _ReplyBubble({required this.reply});
  final TicketReply reply;

  @override
  Widget build(BuildContext context) {
    final isStaff = reply.isStaff;
    final align   = isStaff ? CrossAxisAlignment.end : CrossAxisAlignment.start;
    final color   = reply.isInternal
        ? Colors.yellow.shade100
        : isStaff
            ? Colors.blue.shade50
            : Colors.grey.shade100;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: align,
        children: [
          Row(
            mainAxisAlignment: isStaff ? MainAxisAlignment.end : MainAxisAlignment.start,
            children: [
              if (!isStaff) ...[
                CircleAvatar(radius: 12, backgroundColor: Colors.grey.shade300,
                  child: const Icon(Icons.person, size: 14)),
                const SizedBox(width: 6),
              ],
              Text(reply.authorName, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
              if (reply.isInternal) ...[
                const SizedBox(width: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                  decoration: BoxDecoration(color: Colors.orange, borderRadius: BorderRadius.circular(3)),
                  child: const Text('داخلی', style: TextStyle(fontSize: 9, color: Colors.white)),
                ),
              ],
              if (isStaff) ...[
                const SizedBox(width: 6),
                CircleAvatar(radius: 12, backgroundColor: Colors.blue.shade100,
                  child: const Icon(Icons.support_agent, size: 14, color: Colors.blue)),
              ],
            ],
          ),
          const SizedBox(height: 4),
          ConstrainedBox(
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.only(
                  topLeft:     const Radius.circular(12),
                  topRight:    const Radius.circular(12),
                  bottomLeft:  Radius.circular(isStaff ? 12 : 0),
                  bottomRight: Radius.circular(isStaff ? 0 : 12),
                ),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(reply.message),
                  if (reply.attachments.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Wrap(spacing: 6, children: reply.attachments.map((a) => _AttachmentChip(attachment: a)).toList()),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    _formatTime(reply.createdAt),
                    style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _formatTime(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'همین الان';
    if (diff.inMinutes < 60) return '${diff.inMinutes} دقیقه پیش';
    if (diff.inHours < 24)  return '${diff.inHours} ساعت پیش';
    return '${dt.year}/${dt.month}/${dt.day}';
  }
}

class _AttachmentChip extends StatelessWidget {
  const _AttachmentChip({required this.attachment});
  final TicketAttachment attachment;

  @override
  Widget build(BuildContext context) {
    return Chip(
      avatar: Icon(
        attachment.isImage ? Icons.image : attachment.isPdf ? Icons.picture_as_pdf : Icons.attach_file,
        size: 14,
      ),
      label: Text(attachment.name, style: const TextStyle(fontSize: 11)),
      padding: EdgeInsets.zero,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );
  }
}

// ─── Reply box ────────────────────────────────────────────────────────────────

class _ReplyBox extends StatelessWidget {
  const _ReplyBox({
    required this.controller,
    required this.isSending,
    required this.isInternal,
    required this.onToggleInternal,
    required this.onSend,
  });

  final TextEditingController controller;
  final bool isSending;
  final bool isInternal;
  final VoidCallback onToggleInternal;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(top: BorderSide(color: Colors.grey.shade300)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (isInternal)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              margin: const EdgeInsets.only(bottom: 6),
              decoration: BoxDecoration(
                color: Colors.orange.shade50,
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: Colors.orange.shade200),
              ),
              child: const Text('یادداشت داخلی — برای مشتری نمایش داده نمی‌شود',
                  style: TextStyle(fontSize: 11, color: Colors.orange)),
            ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              IconButton(
                icon: Icon(
                  isInternal ? Icons.lock : Icons.lock_open,
                  color: isInternal ? Colors.orange : Colors.grey,
                ),
                tooltip: isInternal ? 'داخلی' : 'عمومی',
                onPressed: onToggleInternal,
              ),
              Expanded(
                child: TextField(
                  controller: controller,
                  maxLines: null,
                  keyboardType: TextInputType.multiline,
                  textInputAction: TextInputAction.newline,
                  decoration: InputDecoration(
                    hintText: isInternal ? 'یادداشت داخلی...' : 'پاسخ خود را بنویسید...',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(20)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              isSending
                  ? const SizedBox(width: 40, height: 40, child: Center(child: CircularProgressIndicator(strokeWidth: 2)))
                  : IconButton.filled(
                      onPressed: onSend,
                      icon: const Icon(Icons.send),
                      tooltip: 'ارسال',
                    ),
            ],
          ),
        ],
      ),
    );
  }
}
