import 'package:flutter/material.dart';

import '../../core/widgets/alamak_brand_mark.dart';

const _buildName = String.fromEnvironment(
  'FLUTTER_BUILD_NAME',
  defaultValue: '1.0.0',
);
const _buildNumber = String.fromEnvironment(
  'FLUTTER_BUILD_NUMBER',
  defaultValue: '1',
);

class AboutPage extends StatelessWidget {
  const AboutPage({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('درباره برنامه')),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 520),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 12),
                    const Center(child: AlamakBrandMark(size: 96)),
                    const SizedBox(height: 20),
                    Text(
                      'سامانه مدیریت علمک‌ها',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'نسخه $_buildName ($_buildNumber)',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 28),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'مشخصات توسعه‌دهنده',
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            const SizedBox(height: 16),
                            const _ContactRow(
                              icon: Icons.person_outline,
                              label: 'برنامه‌نویس',
                              value: 'سعید ذوالفعلی',
                            ),
                            const SizedBox(height: 14),
                            const _ContactRow(
                              icon: Icons.phone_outlined,
                              label: 'شماره تماس',
                              value: '09397311933',
                              valueDirection: TextDirection.ltr,
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(
                      'اطلاعات این صفحه برای شناسایی نسخه و پشتیبانی برنامه ارائه شده است.',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                        height: 1.7,
                      ),
                    ),
                    SizedBox(height: constraints.maxHeight < 620 ? 8 : 24),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ContactRow extends StatelessWidget {
  const _ContactRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueDirection,
  });

  final IconData icon;
  final String label;
  final String value;
  final TextDirection? valueDirection;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, color: Theme.of(context).colorScheme.primary),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              value,
              textDirection: valueDirection,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w600,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
      ),
    ],
  );
}
