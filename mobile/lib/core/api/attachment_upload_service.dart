import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mime/mime.dart';
import 'package:path/path.dart' as p;

import 'client_api_service.dart';
import 'models/conversation_models.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final attachmentUploadServiceProvider = Provider<AttachmentUploadService>((ref) {
  final api = ref.watch(clientApiServiceProvider);
  return AttachmentUploadService(api: api);
});

// ─── Allowed MIME types and size limits ──────────────────────────────────────

const _allowedMimes = {
  // images
  'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic',
  // documents
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  // archives
  'application/zip', 'application/x-tar', 'application/gzip',
};

const _maxFileSizeBytes = 20 * 1024 * 1024; // 20 MB

// ─── Attachment category ─────────────────────────────────────────────────────

enum AttachmentCategory { image, pdf, document, archive }

AttachmentCategory _detectCategory(String mime) {
  if (mime.startsWith('image/')) return AttachmentCategory.image;
  if (mime == 'application/pdf') return AttachmentCategory.pdf;
  if (mime.contains('zip') || mime.contains('tar') || mime.contains('gzip')) {
    return AttachmentCategory.archive;
  }
  return AttachmentCategory.document;
}

// ─── Upload result ───────────────────────────────────────────────────────────

class AttachmentUploadResult {
  const AttachmentUploadResult({
    required this.attachmentId,
    required this.fileName,
    required this.mimeType,
    required this.sizeBytes,
    required this.url,
    required this.category,
  });

  final String attachmentId;
  final String fileName;
  final String mimeType;
  final int sizeBytes;
  final String url;
  final AttachmentCategory category;

  factory AttachmentUploadResult.fromJson(Map<String, dynamic> j) =>
      AttachmentUploadResult(
        attachmentId: j['id'] as String,
        fileName: j['file_name'] as String,
        mimeType: j['mime_type'] as String,
        sizeBytes: j['size_bytes'] as int,
        url: j['url'] as String,
        category: AttachmentCategory.values.firstWhere(
          (c) => c.name == (j['category'] as String? ?? 'document'),
          orElse: () => AttachmentCategory.document,
        ),
      );
}

// ─── Service ─────────────────────────────────────────────────────────────────

class AttachmentUploadService {
  AttachmentUploadService({required ClientApiService api}) : _api = api;

  final ClientApiService _api;

  /// Validate and upload a file as a multipart attachment to a conversation.
  Future<AttachmentUploadResult> upload(
    String conversationId,
    File file, {
    void Function(int sent, int total)? onProgress,
  }) async {
    await _validate(file);

    final fileName = p.basename(file.path);
    final mime     = lookupMimeType(file.path) ?? 'application/octet-stream';

    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(
        file.path,
        filename: fileName,
        contentType: DioMediaType.parse(mime),
      ),
      'category': _detectCategory(mime).name,
    });

    final data = await _api.uploadAttachment(conversationId, formData, onProgress: onProgress);
    return AttachmentUploadResult.fromJson(data);
  }

  /// Upload from bytes (useful for in-memory captures like camera photos).
  Future<AttachmentUploadResult> uploadBytes(
    String conversationId,
    List<int> bytes,
    String fileName,
    String mimeType, {
    void Function(int sent, int total)? onProgress,
  }) async {
    if (bytes.length > _maxFileSizeBytes) {
      throw const AttachmentValidationException(
        'File exceeds maximum size of 20 MB.',
      );
    }
    _validateMime(mimeType);

    final formData = FormData.fromMap({
      'file': MultipartFile.fromBytes(
        bytes,
        filename: fileName,
        contentType: DioMediaType.parse(mimeType),
      ),
      'category': _detectCategory(mimeType).name,
    });

    final data = await _api.uploadAttachment(conversationId, formData, onProgress: onProgress);
    return AttachmentUploadResult.fromJson(data);
  }

  // ── Private validation ────────────────────────────────────────────────────

  Future<void> _validate(File file) async {
    final size = await file.length();
    if (size > _maxFileSizeBytes) {
      throw const AttachmentValidationException(
        'File exceeds maximum size of 20 MB.',
      );
    }

    final mime = lookupMimeType(file.path) ?? 'application/octet-stream';
    _validateMime(mime);
  }

  void _validateMime(String mime) {
    if (!_allowedMimes.contains(mime)) {
      throw AttachmentValidationException(
        'File type "$mime" is not allowed. Supported: images, PDF, documents, archives.',
      );
    }
  }
}

// ─── Exceptions ───────────────────────────────────────────────────────────────

class AttachmentValidationException implements Exception {
  const AttachmentValidationException(this.message);
  final String message;

  @override
  String toString() => 'AttachmentValidationException: $message';
}
