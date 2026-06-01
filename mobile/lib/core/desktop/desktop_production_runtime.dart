import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:launch_at_startup/launch_at_startup.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:system_tray/system_tray.dart';
import 'package:window_manager/window_manager.dart';

import '../realtime/realtime_client_service.dart';
import '../realtime/realtime_event.dart';
import '../offline/offline_sync_service.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final desktopProductionRuntimeProvider = Provider<DesktopProductionRuntime>((ref) {
  if (!Platform.isWindows && !Platform.isLinux && !Platform.isMacOS) {
    return DesktopProductionRuntime.noop();
  }
  final runtime = DesktopProductionRuntime(ref);
  ref.onDispose(runtime.dispose);
  return runtime;
});

// ─── Window close behavior ────────────────────────────────────────────────────

enum WindowCloseBehavior { minimize, quit }

// ─── Runtime ─────────────────────────────────────────────────────────────────

/// Windows-first production runtime — tray, badge, startup, reconnect on wake
/// and on network recovery.
class DesktopProductionRuntime with WindowListener {
  DesktopProductionRuntime(this._ref) : _noop = false {
    _init();
  }

  DesktopProductionRuntime.noop()
      : _ref  = null,
        _noop = true;

  final Ref? _ref;
  final bool _noop;

  final _tray        = SystemTray();
  final _localPlugin = FlutterLocalNotificationsPlugin();
  int  _unreadCount  = 0;
  bool _initialized  = false;
  WindowCloseBehavior closeBehavior = WindowCloseBehavior.minimize;

  StreamSubscription<RealtimeEvent>? _realtimeSub;
  StreamSubscription<SyncState>? _syncSub;
  Timer? _reconnectTimer;
  VoidCallback? _onShowWindowCallback;

  // ── Initialize ────────────────────────────────────────────────────────────

  Future<void> _init() async {
    if (_noop || _initialized) return;
    _initialized = true;

    await _initWindowManager();
    await _initSystemTray();
    await _initLocalNotifications();
    await _configureLaunchAtStartup();
    _bindRealtimeReconnect();
    _bindOfflineRecovery();
  }

  // ── Window manager ────────────────────────────────────────────────────────

  Future<void> _initWindowManager() async {
    await windowManager.ensureInitialized();
    windowManager.addListener(this);
    await windowManager.setPreventClose(true); // We handle close ourselves
    await windowManager.setTitle('Support Client');
  }

  @override
  void onWindowClose() async {
    if (closeBehavior == WindowCloseBehavior.minimize) {
      await windowManager.hide(); // Minimize to tray
    } else {
      await _quit();
    }
  }

  @override
  void onWindowFocus() {
    // Trigger a realtime reconnect check when the window regains focus
    _triggerReconnect();
    _onShowWindowCallback?.call();
  }

  void setOnShowWindow(VoidCallback cb) => _onShowWindowCallback = cb;

  Future<void> showWindow() async {
    await windowManager.show();
    await windowManager.focus();
    _onShowWindowCallback?.call();
  }

  // ── System tray ───────────────────────────────────────────────────────────

  Future<void> _initSystemTray() async {
    final iconPath = Platform.isWindows
        ? 'assets/icons/tray_icon.ico'
        : 'assets/icons/tray_icon.png';

    await _tray.initSystemTray(title: 'Support Client', iconPath: iconPath);
    await _rebuildTrayMenu();

    _tray.registerSystemTrayEventHandler((eventName) {
      if (eventName == kSystemTrayEventClick ||
          eventName == kSystemTrayEventDoubleClick) {
        showWindow();
      }
    });
  }

  Future<void> _rebuildTrayMenu() async {
    final unreadLabel = _unreadCount > 0 ? 'Open  ($_unreadCount unread)' : 'Open';
    await _tray.setContextMenu(Menu()
      ..buildFrom([
        MenuItemLabel(label: unreadLabel, onClicked: (_) => showWindow()),
        MenuSeparator(),
        MenuItemLabel(
          label: 'Launch at startup',
          onClicked: (_) => toggleLaunchAtStartup(),
        ),
        MenuSeparator(),
        MenuItemLabel(label: 'Quit', onClicked: (_) => _quit()),
      ]));
  }

  // ── Unread badge ──────────────────────────────────────────────────────────

  Future<void> updateUnreadBadge(int count) async {
    if (_noop) return;
    _unreadCount = count;
    final title = count > 0 ? 'Support Client ($count)' : 'Support Client';
    try {
      await _tray.setTitle(title);
      await _rebuildTrayMenu();
      await windowManager.setTitle(title);
    } catch (_) {}
  }

  int get unreadCount => _unreadCount;

  // ── Local notifications ───────────────────────────────────────────────────

  Future<void> _initLocalNotifications() async {
    // flutter_local_notifications 17.x supports Linux; Windows uses tray title
    const linux = LinuxInitializationSettings(defaultActionName: 'Open');
    await _localPlugin.initialize(
      const InitializationSettings(linux: linux),
      onDidReceiveNotificationResponse: (r) => showWindow(),
    );
  }

  Future<void> showNativeNotification({
    required String title,
    required String body,
    String? payload,
  }) async {
    if (_noop) return;

    if (Platform.isLinux) {
      const linuxDetails = LinuxNotificationDetails(urgency: LinuxNotificationUrgency.normal);
      await _localPlugin.show(
        title.hashCode, title, body,
        const NotificationDetails(linux: linuxDetails),
        payload: payload,
      );
    } else if (Platform.isWindows) {
      // Windows: update tray title to indicate new notification
      try { await _tray.setTitle('Support Client (!)'); } catch (_) {}
    }

    await updateUnreadBadge(_unreadCount + 1);
  }

  // ── Startup ───────────────────────────────────────────────────────────────

  Future<void> _configureLaunchAtStartup() async {
    final info = await PackageInfo.fromPlatform();
    launchAtStartup.setup(
      appName:     info.appName,
      appPath:     Platform.resolvedExecutable,
      packageName: 'com.saasplatform.support.client',
    );
  }

  Future<bool> isLaunchAtStartup() async {
    if (_noop) return false;
    return launchAtStartup.isEnabled();
  }

  Future<void> setLaunchAtStartup(bool enabled) async {
    if (_noop) return;
    if (enabled) {
      await launchAtStartup.enable();
    } else {
      await launchAtStartup.disable();
    }
  }

  Future<void> toggleLaunchAtStartup() async {
    final current = await isLaunchAtStartup();
    await setLaunchAtStartup(!current);
  }

  // ── Reconnect on wake + network recovery ─────────────────────────────────

  void _bindRealtimeReconnect() {
    if (_noop || _ref == null) return;
    try {
      final rt = _ref.read(realtimeClientServiceProvider);
      _realtimeSub = rt.events.listen((event) {
        if (event.eventName == 'connection.restored') {
          _reconnectTimer?.cancel();
        }
      });
    } catch (_) {}
  }

  void _bindOfflineRecovery() {
    if (_noop || _ref == null) return;
    try {
      final sync = _ref.read(offlineSyncServiceProvider);
      _syncSub = sync.stateStream.listen((state) {
        if (state.online && !state.syncing) {
          _triggerReconnect();
        }
      });
    } catch (_) {}
  }

  void _triggerReconnect() {
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(const Duration(seconds: 2), () {
      try {
        _ref?.read(realtimeClientServiceProvider).reconnect();
      } catch (_) {}
    });
  }

  // ── Quit ─────────────────────────────────────────────────────────────────

  Future<void> _quit() async {
    windowManager.removeListener(this);
    await windowManager.destroy();
  }

  void dispose() {
    if (_noop) return;
    windowManager.removeListener(this);
    _realtimeSub?.cancel();
    _syncSub?.cancel();
    _reconnectTimer?.cancel();
  }
}
