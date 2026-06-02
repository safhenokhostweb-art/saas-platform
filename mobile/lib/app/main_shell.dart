import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

class MainShell extends ConsumerWidget {
  const MainShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: _onTap,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: const [
          NavigationDestination(
            icon:          Icon(Icons.chat_bubble_outline),
            selectedIcon:  Icon(Icons.chat_bubble),
            label:         'مکالمات',
          ),
          NavigationDestination(
            icon:          Icon(Icons.confirmation_number_outlined),
            selectedIcon:  Icon(Icons.confirmation_number),
            label:         'تیکت‌ها',
          ),
          NavigationDestination(
            icon:          Icon(Icons.dashboard_outlined),
            selectedIcon:  Icon(Icons.dashboard),
            label:         'داشبورد',
          ),
          NavigationDestination(
            icon:          Icon(Icons.settings_outlined),
            selectedIcon:  Icon(Icons.settings),
            label:         'تنظیمات',
          ),
        ],
      ),
    );
  }

  void _onTap(int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }
}
