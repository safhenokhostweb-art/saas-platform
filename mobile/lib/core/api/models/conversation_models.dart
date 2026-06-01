class ConversationSummary {
  const ConversationSummary({
    required this.id,
    required this.title,
    required this.type,
    required this.lastMessage,
    required this.unreadCount,
    required this.updatedAt,
    required this.participantCount,
  });

  final String id;
  final String title;
  final String type;
  final String? lastMessage;
  final int unreadCount;
  final String updatedAt;
  final int participantCount;

  factory ConversationSummary.fromJson(Map<String, dynamic> json) => ConversationSummary(
        id: json['id'].toString(),
        title: json['title'] as String? ?? '',
        type: json['type'] as String? ?? 'direct',
        lastMessage: json['last_message'] as String?,
        unreadCount: json['unread_count'] as int? ?? 0,
        updatedAt: json['updated_at'] as String? ?? '',
        participantCount: json['participant_count'] as int? ?? 0,
      );
}

class ConversationDetail {
  const ConversationDetail({
    required this.id,
    required this.title,
    required this.type,
    required this.participants,
    required this.createdAt,
  });

  final String id;
  final String title;
  final String type;
  final List<ConversationParticipant> participants;
  final String createdAt;

  factory ConversationDetail.fromJson(Map<String, dynamic> json) => ConversationDetail(
        id: json['id'].toString(),
        title: json['title'] as String? ?? '',
        type: json['type'] as String? ?? 'direct',
        participants: (json['participants'] as List<dynamic>?)
                ?.map((e) => ConversationParticipant.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        createdAt: json['created_at'] as String? ?? '',
      );
}

class ConversationParticipant {
  const ConversationParticipant({required this.userId, required this.type, required this.name});

  final String userId;
  final String type;
  final String name;

  factory ConversationParticipant.fromJson(Map<String, dynamic> json) => ConversationParticipant(
        userId: json['user_id'].toString(),
        type: json['type'] as String? ?? 'user',
        name: json['name'] as String? ?? '',
      );
}

class ConversationMessage {
  const ConversationMessage({
    required this.id,
    required this.conversationId,
    required this.senderId,
    required this.senderName,
    required this.content,
    required this.type,
    required this.sentAt,
    this.readAt,
    this.attachments = const [],
  });

  final String id;
  final String conversationId;
  final String senderId;
  final String senderName;
  final String content;
  final String type;
  final String sentAt;
  final String? readAt;
  final List<MessageAttachment> attachments;

  factory ConversationMessage.fromJson(Map<String, dynamic> json) => ConversationMessage(
        id: json['id'].toString(),
        conversationId: json['conversation_id'].toString(),
        senderId: json['sender_id'].toString(),
        senderName: json['sender_name'] as String? ?? '',
        content: json['content'] as String? ?? '',
        type: json['type'] as String? ?? 'text',
        sentAt: json['sent_at'] as String? ?? json['created_at'] as String? ?? '',
        readAt: json['read_at'] as String?,
        attachments: (json['attachments'] as List<dynamic>?)
                ?.map((e) => MessageAttachment.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
      );
}

class MessageAttachment {
  const MessageAttachment({required this.id, required this.name, required this.url, required this.mimeType, required this.size});

  final String id;
  final String name;
  final String url;
  final String mimeType;
  final int size;

  factory MessageAttachment.fromJson(Map<String, dynamic> json) => MessageAttachment(
        id: json['id'].toString(),
        name: json['name'] as String? ?? '',
        url: json['url'] as String? ?? '',
        mimeType: json['mime_type'] as String? ?? '',
        size: json['size'] as int? ?? 0,
      );
}

class MessagePage {
  const MessagePage({required this.messages, required this.nextCursor, required this.hasMore});

  final List<ConversationMessage> messages;
  final String? nextCursor;
  final bool hasMore;

  factory MessagePage.fromJson(Map<String, dynamic> json) => MessagePage(
        messages: (json['data'] as List<dynamic>?)
                ?.map((e) => ConversationMessage.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        nextCursor: json['meta']?['next_cursor'] as String?,
        hasMore: json['meta']?['has_more'] as bool? ?? false,
      );
}

class ConversationMergeResult {
  const ConversationMergeResult({
    required this.targetId,
    required this.mergedCount,
    required this.messagesMoved,
    required this.participantsAdded,
  });

  final String targetId;
  final int mergedCount;
  final int messagesMoved;
  final int participantsAdded;

  factory ConversationMergeResult.fromJson(Map<String, dynamic> json) =>
      ConversationMergeResult(
        targetId:          json['target_id'] as String? ?? '',
        mergedCount:       json['merged_count'] as int? ?? 0,
        messagesMoved:     json['messages_moved'] as int? ?? 0,
        participantsAdded: json['participants_added'] as int? ?? 0,
      );
}

class MergePermission {
  const MergePermission({required this.canMerge});
  final bool canMerge;
  factory MergePermission.fromJson(Map<String, dynamic> json) =>
      MergePermission(canMerge: json['can_merge'] as bool? ?? false);
}
