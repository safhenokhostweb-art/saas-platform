import 'dart:io';

import 'package:flutter/material.dart';
import 'package:window_manager/window_manager.dart';
import 'package:system_tray/system_tray.dart';
import 'package:launch_at_startup/launch_at_startup.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final desktopServiceProvider = Provider<DesktopService>((ref) => DesktopService());

// ─── Desktop client features: tray, badge, native notifications, startup ─────

class DesktopService with WindowListener {
  DesktopService() {
    if (!_isDesktop) return;
    windowManager.addListener(this);
    _initTray();
  }

  bool get _isDesktop => Platform.isWindows || Platform.isLinux || Platform.isMacOS;

  final _tray = SystemTray();
  final _localPlugin = FlutterLocalNotificationsPlugin();
  int _unreadCount = 0;
  VoidCallback? _onShowWindow;

  // ── System tray ───────────────────────────────────────────────────────────

  Future<void> _initTray() async {
    if (!_isDesktop) return;

    await _tray.initSystemTray(
      title: 'Support Client',
      iconPath: Platform.isWindows ? 'assets/icons/tray_icon.ico' : 'assets/icons/tray_icon.png',
    );

    await _tray.setContextMenu(Menu()
      ..buildFrom([
        MenuItemLabel(label: 'Open', onClicked: (_) => _showWindow()),
        MenuItemSeparator(),
        MenuItemLabel(label: 'Quit', onClicked: (_) => _quit()),
      ]));

    _tray.registerSystemTrayEventHandler((eventName) {
      if (eventName == kSystemTrayEventClick || eventName == kSystemTrayEventDoubleClick) {
        _showWindow();
      }
    });
  }

  Future<void> _showWindow() async {
    await windowManager.show();
    await windowManager.focus();
    _onShowWindow?.call();
  }

  void setOnShowWindow(VoidCallback cb) => _onShowWindow = cb;

  // ── Unread badge ──────────────────────────────────────────────────────────

  Future<void> updateUnreadBadge(int count) async {
    _unreadCount = count;
    if (!_isDesktop) return;

    final title = count > 0 ? 'Support Client ($count)' : 'Support Client';
    try {
      await _tray.setTitle(title);
    } catch (_) {}
  }

  int get unreadCount => _unreadCount;

  // ── Native notifications ──────────────────────────────────────────────────

  Future<void> showNativeNotification({required String title, required String body}) async {
    if (!_isDesktop) return;
    // Use tray notification on Windows, local notification on others
    if (Platform.isWindows) {
      try {
        await _tray.setTitle('Support Client (!)');
      } catch (_) {}
    }
    await _localPlugin.show(
      title.hashCode,
      title,
      body,
      const NotificationDetails(
        linux: LinuxNotificationDetails(urgency: LinuxNotificationUrgency.normal),
        macOS: DarwinNotificationDetails(presentAlert: true, presentSound: true),
      ),
    );
  }

  // ── Launch on startup ─────────────────────────────────────────────────────

  Future<bool> isLaunchAtStartup() async {
    if (!_isDesktop) return false;
    return launchAtStartup.isEnabled();
  }

  Future<void> setLaunchAtStartup(bool enabled) async {
    if (!_isDesktop) return;
    if (enabled) {
      await launchAtStartup.enable();
    } else {
      await launchAtStartup.disable();
    }
  }

  // ── Reconnect on wake ─────────────────────────────────────────────────────

  @override
  void onWindowFocus() {
    // Trigger reconnect check when window regains focus
    _onShowWindow?.call();
  }

  Future<void> _quit() async {
    await windowManager.destroy();
  }

  void dispose() {
    if (_isDesktop) windowManager.removeListener(this);
  }
}
