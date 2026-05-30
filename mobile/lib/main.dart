import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:window_manager/window_manager.dart';
import 'package:launch_at_startup/launch_at_startup.dart';
import 'package:package_info_plus/package_info_plus.dart';

import 'app/app.dart';
import 'core/notifications/notification_service.dart';
import 'core/offline/offline_cache_service.dart';
import 'core/security/secure_token_service.dart';
import 'core/ota/ota_update_service.dart';
import 'shared/platform/platform_info.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Desktop window setup
  if (PlatformInfo.isDesktop) {
    await windowManager.ensureInitialized();
    await windowManager.setMinimumSize(const Size(900, 600));
    await windowManager.setTitle('Support Client');

    final info = await PackageInfo.fromPlatform();
    launchAtStartup.setup(
      appName: 'Support Client',
      appPath: info.packageName,
    );
  }

  // Bootstrap core services
  await OfflineCacheService.initialize();

  final container = ProviderContainer(
    overrides: [
      notificationServiceProvider.overrideWith((ref) {
        final service = NotificationService(ref);
        service.initialize();
        return service;
      }),
    ],
  );

  // Prime OTA check after first frame
  WidgetsBinding.instance.addPostFrameCallback((_) async {
    await container.read(otaUpdateServiceProvider).checkForUpdates();
  });

  runApp(UncontrolledProviderScope(container: container, child: const SaasPlatformApp()));
}
