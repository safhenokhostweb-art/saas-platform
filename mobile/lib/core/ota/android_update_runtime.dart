import 'dart:async';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_file/open_file.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';

import 'ota_update_service.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final androidUpdateRuntimeProvider = Provider<AndroidUpdateRuntime>((ref) {
  if (!Platform.isAndroid) return AndroidUpdateRuntime.noop();
  return AndroidUpdateRuntime(ref.watch(otaUpdateServiceProvider.notifier));
});

// ─── Download state ───────────────────────────────────────────────────────────

class AndroidUpdateDownloadState {
  const AndroidUpdateDownloadState({
    this.downloading    = false,
    this.progress       = 0.0,
    this.apkPath,
    this.verified       = false,
    this.error,
    this.readyToInstall = false,
  });

  final bool    downloading;
  final double  progress;
  final String? apkPath;
  final bool    verified;
  final String? error;
  final bool    readyToInstall;
}

// ─── Runtime ─────────────────────────────────────────────────────────────────

/// Android APK download + checksum verification + install prompt.
/// Uses platform OTA endpoints only — no Play Store or external OTA.
class AndroidUpdateRuntime {
  AndroidUpdateRuntime(this._service) : _noop = false;
  AndroidUpdateRuntime.noop() : _service = null, _noop = true;

  final OtaUpdateService? _service;
  final bool _noop;
  final _dio = Dio();

  final _stateController =
      StreamController<AndroidUpdateDownloadState>.broadcast();

  Stream<AndroidUpdateDownloadState> get downloadStateStream =>
      _stateController.stream;

  // ── Check and download ────────────────────────────────────────────────────

  Future<void> checkAndDownload() async {
    if (_noop || _service == null) return;

    final manifest = await _service.checkForUpdates();
    if (manifest == null || !manifest.updateAvailable) return;

    await downloadApk(
      downloadUrl:      manifest.downloadUrl ?? '',
      expectedChecksum: manifest.checksum,
    );
  }

  // ── Download APK ──────────────────────────────────────────────────────────

  Future<String?> downloadApk({
    required String downloadUrl,
    String? expectedChecksum,
  }) async {
    if (_noop) return null;

    _emit(const AndroidUpdateDownloadState(downloading: true));

    try {
      final dir  = await getExternalStorageDirectory();
      final path = '${dir?.path ?? '/sdcard/Download'}/saas-client-update.apk';

      await _dio.download(
        downloadUrl,
        path,
        onReceiveProgress: (received, total) {
          if (total <= 0) return;
          _emit(AndroidUpdateDownloadState(
            downloading: true,
            progress:    received / total,
          ));
        },
      );

      // Checksum verification
      if (expectedChecksum != null) {
        final ok = await _verifyChecksum(path, expectedChecksum);
        if (!ok) {
          _emit(const AndroidUpdateDownloadState(
            error: 'APK checksum verification failed. Download may be corrupt.',
          ));
          await File(path).delete();
          return null;
        }
      }

      _emit(AndroidUpdateDownloadState(
        apkPath:        path,
        verified:       true,
        readyToInstall: true,
      ));

      return path;
    } catch (e) {
      _emit(AndroidUpdateDownloadState(error: e.toString()));
      return null;
    }
  }

  // ── Show update prompt ────────────────────────────────────────────────────

  Future<void> showUpdatePrompt(
    BuildContext context,
    String apkPath, {
    bool mandatory = false,
  }) async {
    if (!context.mounted) return;

    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: !mandatory,
      builder: (_) => AlertDialog(
        title: Text(mandatory ? 'Required Update' : 'Update Available'),
        content: Text(
          mandatory
              ? 'This update is required to continue using Support Client. '
                'Please install it now.'
              : 'A new version of Support Client is ready to install.',
        ),
        actions: [
          if (!mandatory)
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Later'),
            ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Install'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await installApk(apkPath);
    }
  }

  // ── Install APK ───────────────────────────────────────────────────────────

  Future<void> installApk(String apkPath) async {
    if (_noop) return;

    // Request install-packages permission (Android 8+)
    final status = await Permission.requestInstallPackages.request();
    if (!status.isGranted) {
      _emit(const AndroidUpdateDownloadState(
        error: 'Install packages permission denied.',
      ));
      return;
    }

    try {
      await OpenFile.open(apkPath, type: 'application/vnd.android.package-archive');
    } catch (e) {
      _emit(AndroidUpdateDownloadState(error: 'Failed to open installer: $e'));
    }
  }

  // ── SHA-256 checksum verification ─────────────────────────────────────────

  Future<bool> _verifyChecksum(String filePath, String expectedSha256) async {
    final file   = File(filePath);
    final bytes  = await file.readAsBytes();
    final digest = sha256.convert(bytes);
    return digest.toString() == expectedSha256.toLowerCase();
  }

  void _emit(AndroidUpdateDownloadState state) =>
      _stateController.add(state);

  void dispose() => _stateController.close();
}
