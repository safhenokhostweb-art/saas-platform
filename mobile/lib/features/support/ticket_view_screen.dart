import 'dart:async';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_file/open_file.dart';

import '../../core/api/client_api_service.dart';
import '../../shared/providers/auth_provider.dart';

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
  final _scrollCtrl = ScrollController();
  bool _isSending   = false;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      ref.invalidate(ticketDetailProvider(widget.ticketId));
    });
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _sendReply(String text, bool isInternal, List<PlatformFile> files) async {
    setState(() => _isSending = true);
    try {
      await ref.read(clientApiServiceProvider).replyToTicket(
        widget.ticketId,
        text,
        isInternal: isInternal,
        filePaths: files.where((f) => f.path != null).map((f) => f.path!).toList(),
        fileNames: files.map((f) => f.name).toList(),
      );
      // Always refresh regardless of response parsing
      ref.invalidate(ticketDetailProvider(widget.ticketId));
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
          SnackBar(
            content: Text(_friendlyError(e)),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  String _friendlyError(Object e) {
    final s = e.toString();
    if (s.contains('404')) return 'تیکت یافت نشد یا دسترسی ندارید.';
    if (s.contains('403')) return 'دسترسی کافی نیست.';
    if (s.contains('422')) return 'خطای اعتبارسنجی. لطفاً متن پیام را وارد کنید.';
    if (s.contains('SocketException') || s.contains('connection')) return 'خطای اتصال. اتصال اینترنت را بررسی کنید.';
    return 'خطا در ارسال پیام. لطفاً مجدداً تلاش کنید.';
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
          SnackBar(content: Text(_friendlyError(e)), backgroundColor: Colors.red),
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
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('$e'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.invalidate(ticketDetailProvider(widget.ticketId)),
                child: const Text('تلاش مجدد'),
              ),
            ],
          ),
        ),
      ),
      data: (ticket) => _buildContent(ticket),
    );
  }

  Widget _buildContent(TicketDetail ticket) {
    final user        = ref.watch(authStateProvider).user;
    final isStaffUser = !(user?.isEndUser ?? true);

    return Scaffold(
      appBar: AppBar(
        title: Text(ticket.ticketNumber, style: const TextStyle(fontSize: 16)),
        actions: [
          if (isStaffUser)
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
          _TicketHeader(ticket: ticket),
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
          if (!ticket.isClosed)
            _ReplyBox(
              isSending:          _isSending,
              canToggleInternal:  isStaffUser,
              onSend:             _sendReply,
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
            _InfoChip(icon: Icons.flag,   label: ticket.priorityLabel, color: _priorityColor(ticket.priority)),
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

  Color _statusColor(String s) => switch (s) {
    'open'        => Colors.blue,
    'in_progress' => Colors.orange,
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
    final isLeft = reply.isLeftSide; // staff یا site_admin → سمت چپ
    // RTL: start = راست، end = چپ
    final align = isLeft ? CrossAxisAlignment.start : CrossAxisAlignment.end;

    Color bubbleColor;
    Color avatarBgColor;
    Color avatarIconColor;
    IconData avatarIcon;

    if (reply.isInternal) {
      bubbleColor    = Colors.yellow.shade100;
      avatarBgColor  = Colors.orange.shade100;
      avatarIconColor = Colors.orange;
      avatarIcon     = Icons.lock_outline;
    } else if (reply.isSiteAdmin) {
      bubbleColor    = Colors.green.shade50;
      avatarBgColor  = Colors.green.shade100;
      avatarIconColor = Colors.green.shade700;
      avatarIcon     = Icons.admin_panel_settings;
    } else if (reply.isStaff) {
      bubbleColor    = Colors.blue.shade50;
      avatarBgColor  = Colors.blue.shade100;
      avatarIconColor = Colors.blue;
      avatarIcon     = Icons.support_agent;
    } else {
      bubbleColor    = Colors.grey.shade100;
      avatarBgColor  = Colors.grey.shade300;
      avatarIconColor = Colors.grey.shade600;
      avatarIcon     = Icons.person;
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: align,
        children: [
          Row(
            mainAxisAlignment: isLeft ? MainAxisAlignment.start : MainAxisAlignment.end,
            children: [
              if (!isLeft) ...[
                CircleAvatar(radius: 12, backgroundColor: avatarBgColor,
                  child: Icon(avatarIcon, size: 14, color: avatarIconColor)),
                const SizedBox(width: 6),
              ],
              Text(reply.authorName,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
              if (reply.isSiteAdmin) ...[
                const SizedBox(width: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                  decoration: BoxDecoration(color: Colors.green.shade600, borderRadius: BorderRadius.circular(3)),
                  child: const Text('سایت', style: TextStyle(fontSize: 9, color: Colors.white)),
                ),
              ],
              if (reply.isInternal) ...[
                const SizedBox(width: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                  decoration: BoxDecoration(color: Colors.orange, borderRadius: BorderRadius.circular(3)),
                  child: const Text('داخلی', style: TextStyle(fontSize: 9, color: Colors.white)),
                ),
              ],
              if (isLeft) ...[
                const SizedBox(width: 6),
                CircleAvatar(radius: 12, backgroundColor: avatarBgColor,
                  child: Icon(avatarIcon, size: 14, color: avatarIconColor)),
              ],
            ],
          ),
          const SizedBox(height: 4),
          ConstrainedBox(
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: bubbleColor,
                borderRadius: BorderRadius.only(
                  topLeft:     const Radius.circular(12),
                  topRight:    const Radius.circular(12),
                  bottomLeft:  Radius.circular(isLeft ? 12 : 0),
                  bottomRight: Radius.circular(isLeft ? 0 : 12),
                ),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (reply.message.isNotEmpty) Text(reply.message),
                  if (reply.attachments.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Wrap(spacing: 6, children:
                      reply.attachments.map((a) => _AttachmentChip(attachment: a)).toList()),
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

class _AttachmentChip extends ConsumerStatefulWidget {
  const _AttachmentChip({required this.attachment});
  final TicketAttachment attachment;

  @override
  ConsumerState<_AttachmentChip> createState() => _AttachmentChipState();
}

class _AttachmentChipState extends ConsumerState<_AttachmentChip> {
  bool _downloading = false;

  Future<void> _openAttachment() async {
    final url = widget.attachment.downloadUrl;
    if (url == null || url.isEmpty) {
      _showSnack('لینک دانلود موجود نیست.');
      return;
    }

    setState(() => _downloading = true);
    try {
      final api      = ref.read(clientApiServiceProvider);
      final filePath = await api.downloadAttachment(url, widget.attachment.name);
      final result   = await OpenFile.open(filePath);
      if (result.type != ResultType.done && mounted) {
        _showSnack('باز کردن فایل ناموفق: ${result.message}');
      }
    } catch (e) {
      if (mounted) _showSnack('خطا در دانلود فایل.');
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  void _showSnack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), duration: const Duration(seconds: 3)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final att = widget.attachment;
    return GestureDetector(
      onTap: _downloading ? null : _openAttachment,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: att.isImage ? Colors.blue.shade50 : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.grey.shade300),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_downloading)
              const SizedBox(
                width: 14, height: 14,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              Icon(
                att.isImage ? Icons.image
                    : att.isPdf ? Icons.picture_as_pdf
                    : Icons.attach_file,
                size: 14,
                color: att.isImage ? Colors.blue : Colors.grey.shade700,
              ),
            const SizedBox(width: 4),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 120),
              child: Text(
                att.name,
                style: TextStyle(
                  fontSize: 11,
                  color: att.downloadUrl != null ? Colors.blue.shade700 : Colors.grey.shade700,
                  decoration: att.downloadUrl != null ? TextDecoration.underline : null,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (att.size > 0) ...[
              const SizedBox(width: 4),
              Text(
                att.sizeLabel,
                style: TextStyle(fontSize: 9, color: Colors.grey.shade500),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ─── Reply box (stateful — manages text + files + internal toggle) ─────────────

typedef _ReplySubmit = Future<void> Function(String message, bool isInternal, List<PlatformFile> files);

class _ReplyBox extends StatefulWidget {
  const _ReplyBox({
    required this.isSending,
    required this.canToggleInternal,
    required this.onSend,
  });

  final bool          isSending;
  final bool          canToggleInternal;
  final _ReplySubmit  onSend;

  @override
  State<_ReplyBox> createState() => _ReplyBoxState();
}

class _ReplyBoxState extends State<_ReplyBox> {
  final _ctrl               = TextEditingController();
  bool _isInternal          = false;
  final List<PlatformFile> _files = [];

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _pickFiles() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        allowMultiple: true,
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf',
                            'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
      );
      if (result != null && mounted) {
        setState(() => _files.addAll(result.files));
      }
    } catch (_) {}
  }

  void _removeFile(int index) => setState(() => _files.removeAt(index));

  Future<void> _submit() async {
    final text = _ctrl.text.trim();
    if (text.isEmpty && _files.isEmpty) return;
    if (widget.isSending) return;

    final filesCopy = List<PlatformFile>.from(_files);
    await widget.onSend(text, _isInternal, filesCopy);

    // Clear only on successful send (parent resets isSending to false on finish)
    if (mounted) {
      _ctrl.clear();
      setState(() {
        _isInternal = false;
        _files.clear();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(top: BorderSide(color: Colors.grey.shade300)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // ── Internal note banner ─────────────────────────────────────
          if (_isInternal)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              color: Colors.orange.shade50,
              child: const Text(
                'یادداشت داخلی — برای مشتری نمایش داده نمی‌شود',
                style: TextStyle(fontSize: 11, color: Colors.orange),
              ),
            ),

          // ── Selected files preview ────────────────────────────────────
          if (_files.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              color: Colors.grey.shade50,
              child: Wrap(
                spacing: 6,
                runSpacing: 4,
                children: _files.asMap().entries.map((e) {
                  final f = e.value;
                  final isImage = ['jpg','jpeg','png','gif','webp']
                      .contains(f.extension?.toLowerCase());
                  return Chip(
                    avatar: Icon(isImage ? Icons.image : Icons.attach_file, size: 14),
                    label: Text(f.name,
                      style: const TextStyle(fontSize: 11),
                      overflow: TextOverflow.ellipsis),
                    deleteIcon: const Icon(Icons.close, size: 14),
                    onDeleted: () => _removeFile(e.key),
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    padding: EdgeInsets.zero,
                  );
                }).toList(),
              ),
            ),

          // ── Input row ─────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                // Internal toggle (staff only)
                if (widget.canToggleInternal)
                  IconButton(
                    icon: Icon(
                      _isInternal ? Icons.lock : Icons.lock_open,
                      color: _isInternal ? Colors.orange : Colors.grey,
                      size: 20,
                    ),
                    tooltip: _isInternal ? 'یادداشت داخلی' : 'پیام عمومی',
                    onPressed: () => setState(() => _isInternal = !_isInternal),
                    visualDensity: VisualDensity.compact,
                  ),

                // Attach file button
                IconButton(
                  icon: const Icon(Icons.attach_file, size: 20),
                  tooltip: 'پیوست فایل',
                  onPressed: widget.isSending ? null : _pickFiles,
                  color: _files.isNotEmpty ? Colors.blue : Colors.grey,
                  visualDensity: VisualDensity.compact,
                ),

                // Text field
                Expanded(
                  child: TextField(
                    controller: _ctrl,
                    maxLines: null,
                    minLines: 1,
                    keyboardType: TextInputType.multiline,
                    textInputAction: TextInputAction.newline,
                    enabled: !widget.isSending,
                    decoration: InputDecoration(
                      hintText: _isInternal ? 'یادداشت داخلی...' : 'پاسخ خود را بنویسید...',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(20),
                        borderSide: BorderSide(color: Colors.grey.shade300),
                      ),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      isDense: true,
                    ),
                    onSubmitted: (_) {
                      // Ctrl+Enter on desktop behaves as newline; send only via button
                    },
                  ),
                ),

                const SizedBox(width: 6),

                // Send button
                widget.isSending
                    ? const Padding(
                        padding: EdgeInsets.all(8),
                        child: SizedBox(
                          width: 24, height: 24,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : IconButton.filled(
                        onPressed: _submit,
                        icon: const Icon(Icons.send, size: 18),
                        tooltip: 'ارسال',
                        style: IconButton.styleFrom(
                          minimumSize: const Size(38, 38),
                          padding: EdgeInsets.zero,
                        ),
                      ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
