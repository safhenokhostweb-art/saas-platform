import 'package:flutter/material.dart';
import '../../core/api/models/conversation_models.dart';

class ConversationTile extends StatelessWidget {
  const ConversationTile({super.key, required this.conversation, required this.onTap});

  final ConversationSummary conversation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasUnread = conversation.unreadCount > 0;

    return ListTile(
      onTap: onTap,
      leading: Stack(
        clipBehavior: Clip.none,
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: theme.colorScheme.primaryContainer,
            child: Text(
              conversation.title.isNotEmpty ? conversation.title[0].toUpperCase() : '#',
              style: TextStyle(color: theme.colorScheme.onPrimaryContainer, fontWeight: FontWeight.w600),
            ),
          ),
          if (hasUnread)
            Positioned(
              right: -2,
              top: -2,
              child: Container(
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(color: theme.colorScheme.error, shape: BoxShape.circle),
                child: Text('${conversation.unreadCount}', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
              ),
            ),
        ],
      ),
      title: Text(
        conversation.title.isNotEmpty ? conversation.title : 'Conversation ${conversation.id}',
        style: TextStyle(fontWeight: hasUnread ? FontWeight.w700 : FontWeight.normal),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: conversation.lastMessage != null
          ? Text(conversation.lastMessage!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13))
          : null,
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(_formatDate(conversation.updatedAt), style: TextStyle(fontSize: 11, color: theme.colorScheme.outline)),
          const SizedBox(height: 4),
          _typeChip(context, conversation.type),
        ],
      ),
    );
  }

  Widget _typeChip(BuildContext context, String type) {
    final cs = Theme.of(context).colorScheme;
    final color = switch (type) {
      'support' => cs.primary,
      'group' => cs.secondary,
      _ => cs.outline,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
      child: Text(type, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
    );
  }

  String _formatDate(String iso) {
    try {
      final dt = DateTime.parse(iso).toLocal();
      final now = DateTime.now();
      if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
        return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
      }
      return '${dt.month}/${dt.day}';
    } catch (_) {
      return '';
    }
  }
}
