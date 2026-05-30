import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../shared/providers/conversation_provider.dart';
import '../../shared/providers/auth_provider.dart';
import '../../shared/widgets/connection_status_bar.dart';
import '../../shared/widgets/conversation_tile.dart';
import '../../shared/widgets/search_bar_field.dart';
import '../../core/api/models/conversation_models.dart';

class ConversationListScreen extends ConsumerStatefulWidget {
  const ConversationListScreen({super.key});

  @override
  ConsumerState<ConversationListScreen> createState() => _ConversationListScreenState();
}

class _ConversationListScreenState extends ConsumerState<ConversationListScreen> {
  final _searchCtrl = TextEditingController();
  String _searchQuery = '';

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final conversationsAsync = ref.watch(conversationsProvider);
    final user = ref.watch(authStateProvider).user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Conversations'),
        actions: [
          // Unread badge
          conversationsAsync.maybeWhen(
            data: (list) {
              final unread = list.fold<int>(0, (sum, c) => sum + c.unreadCount);
              if (unread == 0) return const SizedBox.shrink();
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: Badge(label: Text('$unread'), child: const Icon(Icons.notifications_outlined)),
              );
            },
            orElse: () => const SizedBox.shrink(),
          ),
          PopupMenuButton<String>(
            onSelected: (v) async {
              if (v == 'logout') {
                await ref.read(authStateProvider.notifier).logout();
                if (mounted) context.go('/login');
              } else if (v == 'settings') {
                context.go('/settings');
              }
            },
            itemBuilder: (_) => [
              PopupMenuItem(value: 'settings', child: Text('Settings')),
              PopupMenuItem(value: 'logout', child: Text('Sign Out')),
            ],
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: CircleAvatar(
                radius: 16,
                child: Text(user?.name.isNotEmpty == true ? user!.name[0].toUpperCase() : '?'),
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          const ConnectionStatusBar(),
          // Search
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: SearchBarField(
              controller: _searchCtrl,
              hint: 'Search conversations…',
              onChanged: (q) => setState(() => _searchQuery = q.toLowerCase()),
            ),
          ),
          Expanded(
            child: conversationsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('Failed to load conversations'),
                    const SizedBox(height: 8),
                    ElevatedButton(
                      onPressed: () => ref.refresh(conversationsProvider),
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
              data: (list) {
                final filtered = _searchQuery.isEmpty
                    ? list
                    : list.where((c) => c.title.toLowerCase().contains(_searchQuery)).toList();

                if (filtered.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.chat_bubble_outline, size: 64, color: Theme.of(context).colorScheme.outline),
                        const SizedBox(height: 12),
                        Text(_searchQuery.isEmpty ? 'No conversations yet' : 'No results for "$_searchQuery"'),
                      ],
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: () async => ref.refresh(conversationsProvider),
                  child: ListView.separated(
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, i) => ConversationTile(
                      conversation: filtered[i],
                      onTap: () => context.go('/conversations/${filtered[i].id}'),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _showNewConversationDialog,
        child: const Icon(Icons.add),
      ),
    );
  }

  void _showNewConversationDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('New Conversation'),
        content: const Text('Create a new support conversation'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              try {
                final api = ref.read(clientApiServiceProvider);
                final userId = ref.read(authStateProvider).user?.id ?? '';
                final conv = await api.createConversation(participants: [userId], type: 'support');
                if (mounted) context.go('/conversations/${conv.id}');
                ref.refresh(conversationsProvider);
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
                }
              }
            },
            child: const Text('Create'),
          ),
        ],
      ),
    );
  }
}
