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
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          const _SectionHeader('Appearance'),
          ListTile(
            title: const Text('Theme'),
            trailing: SegmentedButton<ThemeMode>(
              segments: const [
                ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_outlined), label: Text('Light')),
                ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.brightness_auto_outlined), label: Text('Auto')),
                ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode_outlined), label: Text('Dark')),
              ],
              selected: {themeMode},
              onSelectionChanged: (s) => ref.read(themeModeProvider.notifier).state = s.first,
            ),
          ),
          if (PlatformInfo.isDesktop) ...[
            const _SectionHeader('Desktop'),
            SwitchListTile(
              title: const Text('Launch at startup'),
              subtitle: const Text('Start automatically when you log in'),
              value: _launchAtStartup,
              onChanged: (v) async {
                await ref.read(desktopServiceProvider).setLaunchAtStartup(v);
                setState(() => _launchAtStartup = v);
              },
            ),
          ],
          const _SectionHeader('Connection'),
          ListTile(
            title: const Text('Server URL'),
            subtitle: FutureBuilder<String?>(
              future: ref.read(secureTokenServiceProvider).read(SecureKey.baseUrl),
              builder: (_, snap) => Text(snap.data ?? 'Not configured', style: const TextStyle(fontSize: 12)),
            ),
            trailing: const Icon(Icons.edit_outlined),
          ),
          const _SectionHeader('Update Channel'),
          _UpdateChannelTile(),
          const _SectionHeader('About'),
          ListTile(
            title: const Text('Platform'),
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
      child: Text(title, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Theme.of(context).colorScheme.primary)),
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
        return ListTile(
          title: const Text('Update channel'),
          subtitle: Text(channel),
          trailing: PopupMenuButton<String>(
            onSelected: (v) async {
              await ref.read(secureTokenServiceProvider).write(SecureKey.updateChannel, v);
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'stable', child: Text('Stable')),
              const PopupMenuItem(value: 'beta', child: Text('Beta')),
              const PopupMenuItem(value: 'canary', child: Text('Canary')),
            ],
            child: const Icon(Icons.expand_more),
          ),
        );
      },
    );
  }
}
