import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/client_api_service.dart';
import '../../core/api/models/conversation_models.dart';

final conversationsProvider = FutureProvider.autoDispose<List<ConversationSummary>>((ref) async {
  final api = ref.watch(clientApiServiceProvider);
  return api.getConversations();
});

final conversationDetailProvider =
    FutureProvider.autoDispose.family<ConversationDetail, String>((ref, id) async {
  final api = ref.watch(clientApiServiceProvider);
  return api.getConversation(id);
});
