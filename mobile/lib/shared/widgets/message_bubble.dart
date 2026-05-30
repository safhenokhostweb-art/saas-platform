import 'package:flutter/material.dart';
import '../../core/api/models/conversation_models.dart';

class MessageBubble extends StatelessWidget {
  const MessageBubble({super.key, required this.message, required this.isMe});

  final ConversationMessage message;
  final bool isMe;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMe) ...[
            CircleAvatar(radius: 14, child: Text(message.senderName.isNotEmpty ? message.senderName[0].toUpperCase() : '?')),
            const SizedBox(width: 6),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isMe ? cs.primary : cs.surfaceContainerHigh,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(18),
                  topRight: const Radius.circular(18),
                  bottomLeft: Radius.circular(isMe ? 18 : 4),
                  bottomRight: Radius.circular(isMe ? 4 : 18),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isMe)
                    Text(message.senderName, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: cs.primary)),
                  Text(
                    message.content,
                    style: TextStyle(color: isMe ? cs.onPrimary : cs.onSurface, fontSize: 14),
                  ),
                  if (message.attachments.isNotEmpty) _AttachmentList(attachments: message.attachments),
                  const SizedBox(height: 2),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _formatTime(message.sentAt),
                        style: TextStyle(fontSize: 10, color: isMe ? cs.onPrimary.withOpacity(0.7) : cs.outline),
                      ),
                      if (isMe) ...[
                        const SizedBox(width: 4),
                        Icon(
                          message.readAt != null ? Icons.done_all : Icons.done,
                          size: 12,
                          color: message.readAt != null ? cs.inversePrimary : cs.onPrimary.withOpacity(0.7),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (isMe) const SizedBox(width: 6),
        ],
      ),
    );
  }

  String _formatTime(String iso) {
    try {
      final dt = DateTime.parse(iso).toLocal();
      return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return '';
    }
  }
}

class _AttachmentList extends StatelessWidget {
  const _AttachmentList({required this.attachments});
  final List<MessageAttachment> attachments;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: attachments.map((a) => Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.attach_file, size: 14),
            const SizedBox(width: 4),
            Text(a.name, style: const TextStyle(fontSize: 12, decoration: TextDecoration.underline)),
          ],
        ),
      )).toList(),
    );
  }
}
