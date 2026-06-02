import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/client_api_service.dart';
import '../../shared/providers/conversation_provider.dart';
import '../../shared/providers/auth_provider.dart';
import '../../shared/widgets/connection_status_bar.dart';
import '../../shared/widgets/conversation_tile.dart';
import '../../shared/widgets/search_bar_field.dart';
import '../../core/api/models/conversation_models.dart';

// پروایدر برای کش وضعیت مجوز ادغام کاربر جاری
final mergePermissionProvider = FutureProvider<bool>((ref) async {
  try {
    final perm = await ref.read(clientApiServiceProvider).getMyMergePermission();
    return perm.canMerge;
  } catch (_) {
    return false;
  }
});

class ConversationListScreen extends ConsumerStatefulWidget {
  const ConversationListScreen({super.key});

  @override
  ConsumerState<ConversationListScreen> createState() => _ConversationListScreenState();
}

class _ConversationListScreenState extends ConsumerState<ConversationListScreen> {
  final _searchCtrl = TextEditingController();
  String _searchQuery = '';
  bool   _selectMode  = false;
  final  _selected    = <String>{};

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  // ── حالت انتخاب چندگانه ──────────────────────────────────────────────────

  void _enterSelectMode(String id) => setState(() {
        _selectMode = true;
        _selected.add(id);
      });

  void _toggleSelect(String id) => setState(() {
        if (_selected.contains(id)) {
          _selected.remove(id);
          if (_selected.isEmpty) _selectMode = false;
        } else {
          _selected.add(id);
        }
      });

  void _exitSelectMode() => setState(() {
        _selectMode = false;
        _selected.clear();
      });

  // ── جریان ادغام ──────────────────────────────────────────────────────────

  Future<void> _startMergeFlow(List<ConversationSummary> all) async {
    if (_selected.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('برای ادغام حداقل ۲ مکالمه انتخاب کنید.')),
      );
      return;
    }

    final selectedList = all.where((c) => _selected.contains(c.id)).toList();
    final targetId     = await showDialog<String>(
      context: context,
      builder: (_) => _MergeTargetDialog(conversations: selectedList),
    );
    if (targetId == null || !mounted) return;

    final sourceIds = _selected.where((id) => id != targetId).toList();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تأیید ادغام'),
        content: Text(
          '${sourceIds.length} مکالمه در مکالمه هدف ادغام می‌شود.\n'
          'پیام‌ها و شرکت‌کنندگان منتقل می‌شوند و مکالمات منبع بسته می‌شوند.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('لغو')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.orange.shade700),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('ادغام کن'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await _executeMerge(targetId, sourceIds);
  }

  Future<void> _executeMerge(String targetId, List<String> sourceIds) async {
    try {
      final api    = ref.read(clientApiServiceProvider);
      final result = await api.mergeConversations(targetId: targetId, sourceIds: sourceIds);
      if (!mounted) return;
      _exitSelectMode();
      ref.invalidate(conversationsProvider);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('${result.mergedCount} مکالمه ادغام شد — ${result.messagesMoved} پیام منتقل شد.'),
        backgroundColor: Colors.green.shade700,
      ));
      context.go('/conversations/$targetId');
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('خطا در ادغام: $e'),
        backgroundColor: Colors.red.shade700,
      ));
    }
  }

  // ── ساخت UI ──────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final conversationsAsync = ref.watch(conversationsProvider);
    final canMerge           = ref.watch(mergePermissionProvider).value ?? false;
    final user               = ref.watch(authStateProvider).user;

    return Scaffold(
      appBar: AppBar(
        title: _selectMode
            ? Text('${_selected.length} انتخاب شد')
            : const Text('مکالمات'),
        leading: _selectMode
            ? IconButton(icon: const Icon(Icons.close), onPressed: _exitSelectMode)
            : null,
        actions: [
          // دکمه ادغام (فقط در حالت انتخاب و با مجوز)
          if (_selectMode && canMerge)
            conversationsAsync.maybeWhen(
              data: (list) => TextButton.icon(
                icon:  const Icon(Icons.merge_type, color: Colors.white),
                label: const Text('ادغام', style: TextStyle(color: Colors.white)),
                onPressed: () => _startMergeFlow(list),
              ),
              orElse: () => const SizedBox.shrink(),
            ),
          // حالت عادی — badge + منو
          if (!_selectMode) ...[
            conversationsAsync.maybeWhen(
              data: (list) {
                final unread = list.fold<int>(0, (s, c) => s + c.unreadCount);
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
                  final router = GoRouter.of(context);
                  await ref.read(authStateProvider.notifier).logout();
                  if (!mounted) return;
                  router.go('/login');
                } else if (v == 'settings') {
                  GoRouter.of(context).go('/settings');
                }
              },
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'settings', child: Text('تنظیمات')),
                const PopupMenuItem(value: 'logout',   child: Text('خروج از حساب')),
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
        ],
      ),
      body: Column(
        children: [
          const ConnectionStatusBar(),
          // نوار راهنما در حالت انتخاب
          if (_selectMode)
            ColoredBox(
              color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: .25),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Row(children: [
                  const Icon(Icons.touch_app_outlined, size: 15),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      canMerge
                          ? 'مکالمات را انتخاب کنید. بعد از تأیید، یکی را به عنوان هدف انتخاب خواهید کرد.'
                          : 'شما دسترسی ادغام مکالمات را ندارید.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                ]),
              ),
            ),
          // جستجو
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: SearchBarField(
              controller: _searchCtrl,
              hint: 'جستجو در مکالمات…',
              onChanged: (q) => setState(() => _searchQuery = q.toLowerCase()),
            ),
          ),
          Expanded(
            child: conversationsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error:   (e, _) => Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('خطا در بارگذاری مکالمات'),
                    const SizedBox(height: 8),
                    ElevatedButton(
                      onPressed: () => ref.refresh(conversationsProvider),
                      child: const Text('تلاش مجدد'),
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
                        Text(_searchQuery.isEmpty ? 'هنوز مکالمه‌ای ندارید' : 'نتیجه‌ای برای "$_searchQuery" یافت نشد'),
                      ],
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: () async => ref.refresh(conversationsProvider),
                  child: ListView.separated(
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, i) {
                      final conv       = filtered[i];
                      final isSelected = _selected.contains(conv.id);

                      return InkWell(
                        onTap: _selectMode
                            ? () => _toggleSelect(conv.id)
                            : () => context.go('/conversations/${conv.id}'),
                        onLongPress: canMerge && !_selectMode
                            ? () => _enterSelectMode(conv.id)
                            : null,
                        child: Stack(
                          children: [
                            ConversationTile(
                              conversation: conv,
                              onTap: _selectMode
                                  ? () => _toggleSelect(conv.id)
                                  : () => context.go('/conversations/${conv.id}'),
                            ),
                            if (_selectMode)
                              Positioned(
                                right: 12, top: 0, bottom: 0,
                                child: Center(
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 150),
                                    width: 24, height: 24,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: isSelected
                                          ? Theme.of(context).colorScheme.primary
                                          : Colors.transparent,
                                      border: Border.all(
                                        color: Theme.of(context).colorScheme.primary,
                                        width: 2,
                                      ),
                                    ),
                                    child: isSelected
                                        ? const Icon(Icons.check, size: 16, color: Colors.white)
                                        : null,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
      floatingActionButton: _selectMode
          ? null
          : FloatingActionButton(
              onPressed: _showNewConversationDialog,
              child: const Icon(Icons.add),
            ),
    );
  }

  void _showNewConversationDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('مکالمه جدید'),
        content: const Text('یک مکالمه پشتیبانی جدید ایجاد شود؟'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('انصراف')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              try {
                final api    = ref.read(clientApiServiceProvider);
                final userId = ref.read(authStateProvider).user?.id ?? '';
                final conv   = await api.createConversation(participants: [userId], type: 'support');
                if (mounted) context.go('/conversations/${conv.id}');
                ref.invalidate(conversationsProvider);
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('خطا: $e')));
                }
              }
            },
            child: const Text('ایجاد'),
          ),
        ],
      ),
    );
  }
}

// ─── دیالوگ انتخاب مکالمه هدف برای ادغام ────────────────────────────────────

class _MergeTargetDialog extends StatefulWidget {
  const _MergeTargetDialog({required this.conversations});
  final List<ConversationSummary> conversations;

  @override
  State<_MergeTargetDialog> createState() => _MergeTargetDialogState();
}

class _MergeTargetDialogState extends State<_MergeTargetDialog> {
  String? _targetId;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('مکالمه هدف'),
      content: SizedBox(
        width: double.maxFinite,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'مکالمه‌ای که بقیه در آن ادغام می‌شوند را انتخاب کنید:',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 10),
            ...widget.conversations.map((c) => RadioListTile<String>(
                  dense:         true,
                  contentPadding: EdgeInsets.zero,
                  value:          c.id,
                  // ignore: deprecated_member_use
                  groupValue:     _targetId,
                  // ignore: deprecated_member_use
                  onChanged: (v) => setState(() => _targetId = v),
                  title:    Text(c.title.isEmpty ? 'مکالمه بدون عنوان' : c.title,
                      style: const TextStyle(fontSize: 14)),
                  subtitle: Text(c.type, style: const TextStyle(fontSize: 12)),
                )),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('لغو')),
        ElevatedButton(
          onPressed: _targetId != null ? () => Navigator.pop(context, _targetId) : null,
          child: const Text('تأیید'),
        ),
      ],
    );
  }
}
