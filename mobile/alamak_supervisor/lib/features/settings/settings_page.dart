import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/session_controller.dart';

class SettingsPage extends ConsumerWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Card(child: ListTile(
          leading: const Icon(Icons.dns_outlined),
          title: const Text('Internal Laravel server'),
          subtitle: Text(session.serverUrl ?? 'Not configured', textDirection: TextDirection.ltr),
          trailing: const Icon(Icons.chevron_left),
          onTap: () => context.go('/server'),
        )),
        Card(child: ListTile(
          leading: Icon(session.offline ? Icons.cloud_off : Icons.cloud_done,
              color: session.offline ? Colors.orange : Colors.green),
          title: Text(session.offline ? 'Offline mode' : 'Connected'),
          subtitle: Text(session.user?['name']?.toString() ?? 'Supervisor'),
        )),
        const SizedBox(height: 12),
        FilledButton.tonalIcon(
          onPressed: session.busy ? null : () => ref.read(sessionProvider).refreshUser(),
          icon: const Icon(Icons.sync),
          label: const Text('Refresh account and permissions'),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () async {
            await ref.read(sessionProvider).logout();
            if (context.mounted) context.go('/login');
          },
          icon: const Icon(Icons.logout),
          label: const Text('Secure logout'),
        ),
        const SizedBox(height: 20),
        if (kReleaseMode)
          const Text('ثبت آفلاین در نسخه عملیاتی غیرفعال است؛ پیش‌نویس حساس روی دستگاه ذخیره نمی‌شود.',
              style: TextStyle(color: Colors.deepOrange, height: 1.6)),
        const Text(
          'Local drafts and photos are removed when you sign out. Submitted work is not final until the server confirms it.',
          style: TextStyle(color: Colors.black54, height: 1.6),
        ),
      ]),
    );
  }
}
