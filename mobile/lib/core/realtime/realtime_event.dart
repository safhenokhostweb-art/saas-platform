class RealtimeEvent {
  const RealtimeEvent({
    required this.channel,
    required this.eventName,
    required this.payload,
    required this.receivedAt,
  });

  final String channel;
  final String eventName;
  final Map<String, dynamic> payload;
  final DateTime receivedAt;

  bool get isMessageEvent => eventName == 'App\\Events\\MessageSent' || eventName == 'message.sent';
  bool get isTypingStartEvent => eventName == 'client-typing-start';
  bool get isTypingStopEvent => eventName == 'client-typing-stop';
  bool get isReadReceiptEvent => eventName == 'client-read-receipt' || eventName == 'message.read';
  bool get isPresenceEvent => channel.startsWith('presence-');
  bool get isNotificationEvent => eventName == 'notification.created';
}
