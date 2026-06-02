import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/app.dart';
import '../../core/desktop/desktop_service.dart';
import '../../core/security/secure_token_service.dart';
import '../../shared/platform/platform_info.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  bool _launchAtStartup = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (PlatformInfo.isDesktop) {
      final enabled = await ref.read(desktopServiceProvider).isLaunchAtStartup();
      setState(() => _launchAtStartup = enabled);
    }
  }

  @override
  Widget build(BuildContext context) {
    final themeMode = ref.watch(themeModeProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('تنظیمات')),
      body: ListView(
        children: [
          const _SectionHeader('ظاهر'),
          ListTile(
            title: const Text('پوسته'),
            trailing: SegmentedButton<ThemeMode>(
              segments: const [
                ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_outlined),  label: Text('روشن')),
                ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.brightness_auto_outlined), label: Text('خودکار')),
                ButtonSegment(value: ThemeMode.dark,  icon: Icon(Icons.dark_mode_outlined),   label: Text('تاریک')),
              ],
              selected: {themeMode},
              onSelectionChanged: (s) => ref.read(themeModeProvider.notifier).state = s.first,
            ),
          ),
          if (PlatformInfo.isDesktop) ...[
            const _SectionHeader('دسکتاپ'),
            SwitchListTile(
              title: const Text('اجرا در هنگام روشن شدن ویندوز'),
              subtitle: const Text('اپلیکیشن به صورت خودکار هنگام ورود اجرا می‌شود'),
              value: _launchAtStartup,
              onChanged: (v) async {
                await ref.read(desktopServiceProvider).setLaunchAtStartup(v);
                setState(() => _launchAtStartup = v);
              },
            ),
          ],
          const _SectionHeader('اتصال'),
          ListTile(
            title: const Text('آدرس سرور'),
            subtitle: FutureBuilder<String?>(
              future: ref.read(secureTokenServiceProvider).read(SecureKey.baseUrl),
              builder: (_, snap) => Text(
                snap.data ?? 'تنظیم نشده',
                style: const TextStyle(fontSize: 12),
                textDirection: TextDirection.ltr,
              ),
            ),
            trailing: const Icon(Icons.edit_outlined),
          ),
          const _SectionHeader('کانال بروزرسانی'),
          _UpdateChannelTile(),
          const _SectionHeader('درباره'),
          ListTile(
            title: const Text('پلتفرم'),
            trailing: Text(PlatformInfo.name, style: const TextStyle(fontSize: 13)),
          ),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader(this.title);
  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 4),
      child: Text(
        title,
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Theme.of(context).colorScheme.primary),
      ),
    );
  }
}

class _UpdateChannelTile extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FutureBuilder<String?>(
      future: ref.read(secureTokenServiceProvider).read(SecureKey.updateChannel),
      builder: (context, snap) {
        final channel = snap.data ?? 'stable';
        final label = switch (channel) {
          'stable' => 'پایدار',
          'beta'   => 'آزمایشی',
          'canary' => 'کناری',
          _        => channel,
        };
        return ListTile(
          title: const Text('کانال بروزرسانی'),
          subtitle: Text(label),
          trailing: PopupMenuButton<String>(
            onSelected: (v) async {
              await ref.read(secureTokenServiceProvider).write(SecureKey.updateChannel, v);
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'stable', child: Text('پایدار (Stable)')),
              PopupMenuItem(value: 'beta',   child: Text('آزمایشی (Beta)')),
              PopupMenuItem(value: 'canary', child: Text('کناری (Canary)')),
            ],
            child: const Icon(Icons.expand_more),
          ),
        );
      },
    );
  }
}
