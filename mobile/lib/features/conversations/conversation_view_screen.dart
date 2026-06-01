import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/client_api_service.dart';
import '../../shared/providers/auth_provider.dart';
import '../../shared/providers/realtime_provider.dart';
import '../../shared/widgets/message_bubble.dart';
import '../../shared/widgets/typing_indicator.dart';
import '../../shared/widgets/connection_status_bar.dart';
import '../../shared/widgets/attachment_picker.dart';
import '../../core/api/models/conversation_models.dart';
import '../../core/realtime/realtime_event.dart';

class ConversationViewScreen extends ConsumerStatefulWidget {
  const ConversationViewScreen({super.key, required this.conversationId});
  final String conversationId;

  @override
  ConsumerState<ConversationViewScreen> createState() => _ConversationViewScreenState();
}

class _ConversationViewScreenState extends ConsumerState<ConversationViewScreen> {
  final _inputCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  final _messages = <ConversationMessage>[];
  final _typingUsers = <String>{};

  bool _loading = true;
  bool _sending = false;
  String? _cursor;
  bool _hasMore = false;
  StreamSubscription<RealtimeEvent>? _rtSub;
  Timer? _typingTimer;
  bool _isTyping = false;

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _subscribeRealtime();
  }

  @override
  void dispose() {
    _inputCtrl.dispose();
    _scrollCtrl.dispose();
    _rtSub?.cancel();
    _typingTimer?.cancel();
    // Unsubscribe from conversation channel
    ref.read(realtimeServiceProvider).unsubscribeFromConversation(widget.conversationId);
    super.dispose();
  }

  Future<void> _loadMessages({bool loadMore = false}) async {
    if (!loadMore) setState(() => _loading = true);
    try {
      final api = ref.read(clientApiServiceProvider);
      final page = await api.getMessages(widget.conversationId, cursor: loadMore ? _cursor : null);
      setState(() {
        if (loadMore) {
          _messages.insertAll(0, page.messages);
        } else {
          _messages
            ..clear()
            ..addAll(page.messages);
        }
        _cursor = page.nextCursor;
        _hasMore = page.hasMore;
        _loading = false;
      });
      if (!loadMore) _scrollToBottom();
    } catch (e) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _subscribeRealtime() async {
    final rt = ref.read(realtimeServiceProvider);
    await rt.subscribeToConversation(widget.conversationId);

    _rtSub = rt.events.listen((event) {
      if (!event.channel.contains(widget.conversationId)) return;

      if (event.isMessageEvent) {
        final msg = ConversationMessage.fromJson(event.payload);
        setState(() {
          _messages.add(msg);
          _typingUsers.remove(msg.senderId);
        });
        _scrollToBottom();
        rt.sendReadReceipt(widget.conversationId, msg.id);
      } else if (event.isTypingStartEvent) {
        setState(() => _typingUsers.add(event.payload['user_id'] as String? ?? ''));
      } else if (event.isTypingStopEvent) {
        setState(() => _typingUsers.remove(event.payload['user_id'] as String? ?? ''));
      } else if (event.isReadReceiptEvent) {
        final msgId = event.payload['message_id'] as String?;
        if (msgId != null) {
          setState(() {
            final idx = _messages.indexWhere((m) => m.id == msgId);
            if (idx != -1) {
              // Message found — update read state (immutable copy)
              _messages[idx] = ConversationMessage(
                id: _messages[idx].id,
                conversationId: _messages[idx].conversationId,
                senderId: _messages[idx].senderId,
                senderName: _messages[idx].senderName,
                content: _messages[idx].content,
                type: _messages[idx].type,
                sentAt: _messages[idx].sentAt,
                readAt: DateTime.now().toIso8601String(),
              );
            }
          });
        }
      }
    });
  }

  void _onInputChanged(String text) {
    final rt = ref.read(realtimeServiceProvider);
    if (text.isNotEmpty && !_isTyping) {
      _isTyping = true;
      rt.sendTypingStart(widget.conversationId);
    }
    _typingTimer?.cancel();
    _typingTimer = Timer(const Duration(seconds: 3), () {
      _isTyping = false;
      rt.sendTypingStop(widget.conversationId);
    });
  }

  Future<void> _sendMessage() async {
    final text = _inputCtrl.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() { _sending = true; _isTyping = false; });
    _typingTimer?.cancel();
    _inputCtrl.clear();

    try {
      final api = ref.read(clientApiServiceProvider);
      final msg = await api.sendMessage(widget.conversationId, text);
      setState(() => _messages.add(msg));
      _scrollToBottom();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Send failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final currentUserId = ref.watch(authStateProvider).user?.id ?? '';

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.conversationId, style: const TextStyle(fontSize: 16)),
            if (_typingUsers.isNotEmpty)
              Text('typing…', style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.primary)),
          ],
        ),
        actions: [
          IconButton(icon: const Icon(Icons.info_outline), onPressed: () {}),
        ],
      ),
      body: Column(
        children: [
          const ConnectionStatusBar(),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : NotificationListener<ScrollNotification>(
                    onNotification: (n) {
                      if (n is ScrollStartNotification && _scrollCtrl.position.pixels == 0 && _hasMore) {
                        _loadMessages(loadMore: true);
                      }
                      return false;
                    },
                    child: ListView.builder(
                      controller: _scrollCtrl,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      itemCount: _messages.length + (_typingUsers.isNotEmpty ? 1 : 0),
                      itemBuilder: (context, i) {
                        if (i == _messages.length) return const TypingIndicator();
                        final msg = _messages[i];
                        return MessageBubble(
                          message: msg,
                          isMe: msg.senderId == currentUserId,
                        );
                      },
                    ),
                  ),
          ),

          // Input bar
          SafeArea(
            child: Container(
              decoration: BoxDecoration(
                border: Border(top: BorderSide(color: Theme.of(context).dividerColor)),
                color: Theme.of(context).colorScheme.surface,
              ),
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: Row(
                children: [
                  AttachmentPickerButton(conversationId: widget.conversationId),
                  const SizedBox(width: 8),
                  Expanded(
                    child: TextField(
                      controller: _inputCtrl,
                      onChanged: _onInputChanged,
                      onSubmitted: (_) => _sendMessage(),
                      maxLines: null,
                      textInputAction: TextInputAction.send,
                      decoration: InputDecoration(
                        hintText: 'Type a message…',
                        isDense: true,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(24)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _sendMessage,
                    icon: _sending
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
