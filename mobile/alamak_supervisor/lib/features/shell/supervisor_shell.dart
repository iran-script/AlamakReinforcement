import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../dashboard/dashboard_page.dart';
import '../map/map_page.dart';
import '../cases/case_pages.dart';
import '../settings/settings_page.dart';

class SupervisorShell extends StatelessWidget {
  const SupervisorShell({super.key, required this.tab});
  final String tab;

  static const _tabs = ['home', 'map', 'cases', 'settings'];

  @override
  Widget build(BuildContext context) {
    final index = _tabs.indexOf(tab).clamp(0, _tabs.length - 1);
    final pages = [const DashboardPage(), const MapPage(), const CasesPage(), const SettingsPage()];
    return Scaffold(
      body: SafeArea(child: IndexedStack(index: index, children: pages)),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (value) => context.go('/app/${_tabs[value]}'),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'خانه'),
          NavigationDestination(icon: Icon(Icons.map_outlined), selectedIcon: Icon(Icons.map), label: 'نقشه'),
          NavigationDestination(icon: Icon(Icons.assignment_outlined), selectedIcon: Icon(Icons.assignment), label: 'پرونده‌ها'),
          NavigationDestination(icon: Icon(Icons.settings_outlined), selectedIcon: Icon(Icons.settings), label: 'تنظیمات'),
        ],
      ),
    );
  }
}
