import 'dart:async';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';

import 'ota_update_service.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final windowsUpdateRuntimeProvider = Provider<WindowsUpdateRuntime>((ref) {
  if (!Platform.isWindows) return WindowsUpdateRuntime.noop();
  return WindowsUpdateRuntime(ref.watch(otaUpdateServiceProvider.notifier));
});

// ─── Download state ───────────────────────────────────────────────────────────

class WindowsUpdateDownloadState {
  const WindowsUpdateDownloadState({
    this.downloading  = false,
    this.progress     = 0.0,
    this.downloadPath,
    this.verified     = false,
    this.error,
    this.readyToInstall = false,
  });

  final bool    downloading;
  final double  progress;       // 0.0–1.0
  final String? downloadPath;
  final bool    verified;
  final String? error;
  final bool    readyToInstall;
}

// ─── Runtime ─────────────────────────────────────────────────────────────────

/// Windows auto-update — version check, package download, checksum verify,
/// restart prompt. Uses platform OTA endpoints exclusively.
class WindowsUpdateRuntime {
  WindowsUpdateRuntime(this._service) : _noop = false;
  WindowsUpdateRuntime.noop() : _service = null, _noop = true;

  final OtaUpdateService? _service;
  final bool _noop;
  final _dio = Dio();

  final _stateController =
      StreamController<WindowsUpdateDownloadState>.broadcast();

  Stream<WindowsUpdateDownloadState> get downloadStateStream =>
      _stateController.stream;

  // ── Check and download if available ──────────────────────────────────────

  Future<void> checkAndDownload() async {
    if (_noop || _service == null) return;

    final manifest = await _service!.checkForUpdates();
    if (manifest == null || !manifest.updateAvailable) return;

    await download(
      downloadUrl: manifest.downloadUrl ?? '',
      expectedChecksum: manifest.checksum,
    );
  }

  // ── Download package ─────────────────────────────────────────────────────

  Future<String?> download({
    required String downloadUrl,
    String? expectedChecksum,
  }) async {
    if (_noop) return null;

    _emit(const WindowsUpdateDownloadState(downloading: true));

    try {
      final dir  = await getApplicationSupportDirectory();
      final path = '${dir.path}\\saas-client-update.exe';

      await _dio.download(
        downloadUrl,
        path,
        onReceiveProgress: (received, total) {
          if (total <= 0) return;
          _emit(WindowsUpdateDownloadState(
            downloading: true,
            progress:    received / total,
          ));
        },
      );

      // Checksum verification
      if (expectedChecksum != null) {
        final ok = await _verifyChecksum(path, expectedChecksum);
        if (!ok) {
          _emit(const WindowsUpdateDownloadState(
            error: 'Checksum verification failed. Update aborted.',
          ));
          await File(path).delete();
          return null;
        }
      }

      _emit(WindowsUpdateDownloadState(
        downloadPath:   path,
        verified:       true,
        readyToInstall: true,
      ));

      return path;
    } catch (e) {
      _emit(WindowsUpdateDownloadState(error: e.toString()));
      return null;
    }
  }

  // ── Show restart prompt dialog ────────────────────────────────────────────

  Future<void> showRestartPrompt(BuildContext context, String installerPath) async {
    if (!context.mounted) return;

    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        title: const Text('Update Ready'),
        content: const Text(
          'A new version of Support Client has been downloaded. '
          'The app will restart to apply the update.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Later'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Restart & Update'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await _launchInstaller(installerPath);
    }
  }

  // ── Launch installer and quit ─────────────────────────────────────────────

  Future<void> _launchInstaller(String path) async {
    try {
      await Process.start(
        path,
        ['/SILENT'],
        mode: ProcessStartMode.detached,
      );
      exit(0);
    } catch (e) {
      _emit(WindowsUpdateDownloadState(error: 'Failed to launch installer: $e'));
    }
  }

  // ── SHA-256 checksum verification ────────────────────────────────────────

  Future<bool> _verifyChecksum(String filePath, String expectedSha256) async {
    final file    = File(filePath);
    final bytes   = await file.readAsBytes();
    final digest  = sha256.convert(bytes);
    return digest.toString() == expectedSha256.toLowerCase();
  }

  void _emit(WindowsUpdateDownloadState state) =>
      _stateController.add(state);

  void dispose() => _stateController.close();
}
