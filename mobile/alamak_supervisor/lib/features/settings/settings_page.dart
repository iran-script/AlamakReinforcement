import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/session_controller.dart';

class SettingsPage extends ConsumerWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('تنظیمات')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: ListTile(
                leading: const Icon(Icons.dns_outlined),
                title: const Text('نشانی سرور سامانه'),
                subtitle: Text(
                  session.serverUrl ?? 'تنظیم نشده',
                  textDirection: TextDirection.ltr,
                ),
                trailing: const Icon(Icons.chevron_left),
                onTap: () => context.go('/server'),
              ),
            ),
            const SizedBox(height: 8),
            Card(
              child: ListTile(
                leading: Icon(
                  session.offline ? Icons.cloud_off : Icons.cloud_done,
                  color: session.offline ? Colors.orange : Colors.green,
                ),
                title: Text(session.offline ? 'حالت آفلاین' : 'متصل'),
                subtitle: Text(session.user?['name']?.toString() ?? 'ناظر'),
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.tonalIcon(
              onPressed: session.busy
                  ? null
                  : () => ref.read(sessionProvider).refreshUser(),
              icon: const Icon(Icons.sync),
              label: const Text('به‌روزرسانی حساب و مجوزها'),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: () async {
                await ref.read(sessionProvider).logout();
                if (context.mounted) context.go('/login');
              },
              icon: const Icon(Icons.logout),
              label: const Text('خروج امن از حساب'),
            ),
            const SizedBox(height: 12),
            Card(
              child: ListTile(
                leading: const Icon(Icons.info_outline),
                title: const Text('درباره برنامه'),
                subtitle: const Text('نسخه و مشخصات برنامه‌نویس'),
                trailing: const Icon(Icons.chevron_left),
                onTap: () => context.push('/about'),
              ),
            ),
            const SizedBox(height: 20),
            if (kReleaseMode)
              Text(
                'ذخیره پیش‌نویس آفلاین در نسخه عملیاتی غیرفعال است؛ پیش‌نویس حساس روی دستگاه ذخیره نمی‌شود.',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  height: 1.6,
                ),
              ),
            Text(
              'پیش‌نویس‌ها و عکس‌های محلی هنگام خروج از حساب پاک می‌شوند. ثبت عملیات پس از تأیید سرور نهایی است.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                height: 1.6,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
