class PresenceStatus {
  const PresenceStatus({required this.userId, required this.status, required this.lastSeen});

  final String userId;
  final String status; // online | away | offline
  final String? lastSeen;

  factory PresenceStatus.fromJson(Map<String, dynamic> json) => PresenceStatus(
        userId: json['user_id'].toString(),
        status: json['status'] as String? ?? 'offline',
        lastSeen: json['last_seen'] as String?,
      );
}
