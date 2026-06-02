import 'package:flutter/foundation.dart';

DateTime _parseDate(dynamic value) {
  if (value == null) return DateTime.now();
  try { return DateTime.parse(value as String); } catch (_) { return DateTime.now(); }
}

// ─── Ticket summary (list item) ──────────────────────────────────────────────

@immutable
class TicketSummary {
  const TicketSummary({
    required this.id,
    required this.ticketNumber,
    required this.subject,
    required this.status,
    required this.priority,
    this.category,
    this.customerName,
    this.customerMobile,
    this.assigneeName,
    required this.createdAt,
    required this.updatedAt,
  });

  final int id;
  final String ticketNumber;
  final String subject;
  final String status;
  final String priority;
  final String? category;
  final String? customerName;
  final String? customerMobile;
  final String? assigneeName;
  final DateTime createdAt;
  final DateTime updatedAt;

  factory TicketSummary.fromJson(Map<String, dynamic> j) => TicketSummary(
        id:              j['id'] as int? ?? 0,
        ticketNumber:    (j['ticket_number'] as String?) ?? '',
        subject:         (j['subject'] as String?) ?? '',
        status:          (j['status'] as String?) ?? 'open',
        priority:        (j['priority'] as String?) ?? 'normal',
        category:        j['category'] as String?,
        customerName:    j['customer_name'] as String?,
        customerMobile:  j['customer_mobile'] as String?,
        assigneeName:    j['assignee_name'] as String?,
        createdAt:       _parseDate(j['created_at']),
        updatedAt:       _parseDate(j['updated_at']),
      );

  bool get isOpen       => status == 'open';
  bool get isInProgress => status == 'in_progress';
  bool get isClosed     => status == 'closed';
  bool get isHigh       => priority == 'high';

  String get statusLabel {
    switch (status) {
      case 'open':        return 'باز';
      case 'in_progress': return 'در حال بررسی';
      case 'closed':      return 'بسته';
      default:            return status;
    }
  }

  String get priorityLabel {
    switch (priority) {
      case 'low':    return 'پایین';
      case 'normal': return 'عادی';
      case 'high':   return 'بالا';
      default:       return priority;
    }
  }
}

// ─── Ticket reply ────────────────────────────────────────────────────────────

@immutable
class TicketReply {
  const TicketReply({
    required this.id,
    required this.message,
    required this.authorType,
    required this.authorName,
    required this.isInternal,
    required this.attachments,
    required this.createdAt,
  });

  final int id;
  final String message;
  final String authorType;
  final String authorName;
  final bool isInternal;
  final List<TicketAttachment> attachments;
  final DateTime createdAt;

  bool get isStaff    => authorType == 'staff';
  bool get isCustomer => authorType == 'customer';

  factory TicketReply.fromJson(Map<String, dynamic> j) => TicketReply(
        id:          j['id'] as int? ?? 0,
        message:     (j['message'] as String?) ?? '',
        authorType:  (j['author_type'] as String?) ?? 'staff',
        authorName:  (j['author_name'] as String?) ?? ((j['author_type'] == 'staff') ? 'پشتیبان' : 'مشتری'),
        isInternal:  j['is_internal'] as bool? ?? false,
        attachments: (j['attachments'] as List<dynamic>? ?? [])
            .whereType<Map<String, dynamic>>()
            .map(TicketAttachment.fromJson)
            .toList(),
        createdAt:   _parseDate(j['created_at']),
      );
}

// ─── Ticket attachment ───────────────────────────────────────────────────────

@immutable
class TicketAttachment {
  const TicketAttachment({
    required this.name,
    required this.path,
    required this.size,
    required this.mime,
  });

  final String name;
  final String path;
  final int size;
  final String mime;

  bool get isImage => mime.startsWith('image/');
  bool get isPdf   => mime == 'application/pdf';

  String get sizeLabel {
    if (size < 1024) return '$size B';
    if (size < 1024 * 1024) return '${(size / 1024).toStringAsFixed(1)} KB';
    return '${(size / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  factory TicketAttachment.fromJson(Map<String, dynamic> j) => TicketAttachment(
        name: j['name'] as String,
        path: j['path'] as String,
        size: j['size'] as int? ?? 0,
        mime: j['mime'] as String? ?? 'application/octet-stream',
      );
}

// ─── Ticket detail (full ticket) ────────────────────────────────────────────

@immutable
class TicketDetail {
  const TicketDetail({
    required this.id,
    required this.ticketNumber,
    required this.subject,
    required this.description,
    required this.status,
    required this.priority,
    this.category,
    this.customerName,
    this.customerMobile,
    this.assigneeName,
    required this.tags,
    required this.attachments,
    required this.replies,
    required this.createdAt,
    required this.updatedAt,
    this.closedAt,
    this.firstResponseAt,
    this.resolvedAt,
  });

  final int id;
  final String ticketNumber;
  final String subject;
  final String description;
  final String status;
  final String priority;
  final String? category;
  final String? customerName;
  final String? customerMobile;
  final String? assigneeName;
  final List<String> tags;
  final List<TicketAttachment> attachments;
  final List<TicketReply> replies;
  final DateTime createdAt;
  final DateTime updatedAt;
  final DateTime? closedAt;
  final DateTime? firstResponseAt;
  final DateTime? resolvedAt;

  bool get isOpen       => status == 'open';
  bool get isInProgress => status == 'in_progress';
  bool get isClosed     => status == 'closed';

  String get statusLabel => switch (status) {
    'open'        => 'باز',
    'in_progress' => 'در حال بررسی',
    'closed'      => 'بسته',
    _             => status,
  };

  String get priorityLabel => switch (priority) {
    'low'    => 'پایین',
    'normal' => 'عادی',
    'high'   => 'بالا',
    _        => priority,
  };

  factory TicketDetail.fromJson(Map<String, dynamic> j) => TicketDetail(
        id:              j['id'] as int? ?? 0,
        ticketNumber:    (j['ticket_number'] as String?) ?? '',
        subject:         (j['subject'] as String?) ?? '',
        description:     (j['description'] as String?) ?? (j['message'] as String?) ?? '',
        status:          (j['status'] as String?) ?? 'open',
        priority:        (j['priority'] as String?) ?? 'normal',
        category:        j['category'] as String?,
        customerName:    j['customer_name'] as String?,
        customerMobile:  j['customer_mobile'] as String?,
        assigneeName:    j['assignee_name'] as String?,
        tags:            (j['tags'] as List<dynamic>? ?? []).whereType<String>().toList(),
        attachments:     (j['attachments'] as List<dynamic>? ?? [])
            .whereType<Map<String, dynamic>>()
            .map(TicketAttachment.fromJson)
            .toList(),
        replies:         (j['replies'] as List<dynamic>? ?? [])
            .whereType<Map<String, dynamic>>()
            .map(TicketReply.fromJson)
            .toList(),
        createdAt:       _parseDate(j['created_at']),
        updatedAt:       _parseDate(j['updated_at']),
        closedAt:        j['closed_at'] != null ? _parseDate(j['closed_at']) : null,
        firstResponseAt: j['first_response_at'] != null ? _parseDate(j['first_response_at']) : null,
        resolvedAt:      j['resolved_at'] != null ? _parseDate(j['resolved_at']) : null,
      );
}

// ─── Paginated ticket list ───────────────────────────────────────────────────

@immutable
class TicketPage {
  const TicketPage({
    required this.tickets,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.perPage,
  });

  final List<TicketSummary> tickets;
  final int currentPage;
  final int lastPage;
  final int total;
  final int perPage;

  bool get hasMore => currentPage < lastPage;

  factory TicketPage.fromJson(Map<String, dynamic> j) {
    final data = j['data'] as List<dynamic>;
    final meta = j['meta'] as Map<String, dynamic>;
    return TicketPage(
      tickets:     data.map((e) => TicketSummary.fromJson(e as Map<String, dynamic>)).toList(),
      currentPage: meta['current_page'] as int,
      lastPage:    meta['last_page']    as int,
      total:       meta['total']        as int,
      perPage:     meta['per_page']     as int,
    );
  }
}
