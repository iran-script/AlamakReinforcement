import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/alamak_brand_mark.dart';
import 'startup_coordinator.dart';
import 'session_controller.dart';

class LoadingPage extends StatelessWidget {
  const LoadingPage({required this.startup, super.key});

  final StartupCoordinator startup;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return AnimatedBuilder(
      animation: startup,
      builder: (context, _) => Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 24,
                      vertical: 30,
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'سعید ذوالفعلی',
                          textAlign: TextAlign.center,
                          style: Theme.of(context)
                              .textTheme
                              .headlineSmall
                              ?.copyWith(
                                color: colors.primary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'برنامه‌نویس: طراحی و توسعه نرم‌افزار',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'سامانه مدیریت علمک‌ها',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        const SizedBox(height: 14),
                        const SelectableText(
                          '09397311933',
                          textDirection: TextDirection.ltr,
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1.2,
                          ),
                        ),
                        const SizedBox(height: 20),
                        const AlamakBrandMark(size: 58),
                        const SizedBox(height: 20),
                        if (startup.errorMessage == null) ...[
                          SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.5,
                              color: colors.primary,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            'در حال آماده‌سازی برنامه…',
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(color: colors.onSurfaceVariant),
                          ),
                        ] else ...[
                          Icon(
                            Icons.error_outline,
                            color: colors.error,
                            size: 28,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            startup.errorMessage!,
                            textAlign: TextAlign.center,
                            style: TextStyle(color: colors.error),
                          ),
                          const SizedBox(height: 12),
                          FilledButton.icon(
                            onPressed: startup.running ? null : startup.retry,
                            icon: const Icon(Icons.refresh),
                            label: const Text('تلاش دوباره'),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class ServerSetupPage extends ConsumerStatefulWidget {
  const ServerSetupPage({super.key});
  @override
  ConsumerState<ServerSetupPage> createState() => _ServerSetupPageState();
}

class _ServerSetupPageState extends ConsumerState<ServerSetupPage> {
  final _url = TextEditingController();

  @override
  void initState() {
    super.initState();
    _url.text = ref.read(sessionProvider).serverUrl ?? '';
  }

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: ListView(
              padding: const EdgeInsets.all(24),
              shrinkWrap: true,
              children: [
                const _BrandHeader(
                  icon: Icons.router_outlined,
                  title: 'اتصال به سامانه',
                  subtitle: 'تغییر نشانی سرور',
                ),
                const SizedBox(height: 26),
                TextField(
                  controller: _url,
                  keyboardType: TextInputType.url,
                  textDirection: TextDirection.ltr,
                  decoration: const InputDecoration(
                    labelText:
                        'در محیط عملیاتی HTTPS الزامی است. در توسعه، HTTP فقط برای میزبان محلی مجاز است.',
                    hintText: 'https://alamak.example.local',
                    prefixIcon: Icon(Icons.link),
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'در محیط عملیاتی HTTPS الزامی است. در توسعه، HTTP فقط برای میزبان محلی مجاز است.',
                  style: TextStyle(color: Colors.black54, height: 1.6),
                ),
                if (session.error != null) ...[
                  const SizedBox(height: 12),
                  _ErrorBanner(session.error!),
                ],
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: session.busy
                      ? null
                      : () async {
                          final ok = await ref
                              .read(sessionProvider)
                              .configureServer(_url.text);
                          if (ok && context.mounted) context.go('/login');
                        },
                  icon: session.busy
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.verified_user_outlined),
                  label: const Text('بررسی و ذخیره سرور'),
                ),
                TextButton(
                  onPressed: () => context.go('/login'),
                  child: const Text('ورود ناظر'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class SupervisorLoginPage extends ConsumerStatefulWidget {
  const SupervisorLoginPage({super.key});
  @override
  ConsumerState<SupervisorLoginPage> createState() =>
      _SupervisorLoginPageState();
}

class _SupervisorLoginPageState extends ConsumerState<SupervisorLoginPage> {
  final _username = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: ListView(
              padding: const EdgeInsets.all(24),
              shrinkWrap: true,
              children: [
                const _BrandHeader(
                  icon: Icons.engineering_outlined,
                  title: 'ورود ناظر',
                  subtitle: 'ورود ناظر',
                ),
                const SizedBox(height: 24),
                TextField(
                  controller: _username,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.username],
                  decoration: const InputDecoration(
                    labelText: 'نام کاربری',
                    prefixIcon: Icon(Icons.person_outline),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  obscureText: _obscure,
                  textInputAction: TextInputAction.done,
                  autofillHints: const [AutofillHints.password],
                  onSubmitted: (_) => _login(),
                  decoration: InputDecoration(
                    labelText: 'رمز عبور',
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      tooltip: _obscure ? 'نمایش رمز' : 'رمز عبور',
                      onPressed: () => setState(() => _obscure = !_obscure),
                      icon: Icon(
                        _obscure
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                      ),
                    ),
                  ),
                ),
                if (session.error != null) ...[
                  const SizedBox(height: 12),
                  _ErrorBanner(session.error!),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: session.busy ? null : _login,
                  child: session.busy
                      ? const CircularProgressIndicator()
                      : const Text('ورود امن'),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: () => context.go('/server'),
                  icon: const Icon(Icons.router_outlined),
                  label: Text(session.serverUrl ?? 'تغییر نشانی سرور'),
                ),
                if (session.offline)
                  const Padding(
                    padding: EdgeInsets.only(top: 16),
                    child: _ErrorBanner(
                      'اتصال به سرور برقرار نیست. پس از بازگشت اتصال دوباره تلاش کنید.',
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _login() async {
    final ok = await ref
        .read(sessionProvider)
        .login(_username.text, _password.text);
    if (ok && mounted) context.go('/app/home');
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader({
    required this.icon,
    required this.title,
    required this.subtitle,
  });
  final IconData icon;
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 58,
        height: 58,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Icon(
          icon,
          size: 30,
          color: Theme.of(context).colorScheme.primary,
        ),
      ),
      const SizedBox(height: 18),
      Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 6),
      Text(
        subtitle,
        style: const TextStyle(color: Colors.black54, height: 1.6),
      ),
    ],
  );
}

class ErrorBanner extends StatelessWidget {
  const ErrorBanner(this.message, {super.key});
  final String message;
  @override
  Widget build(BuildContext context) => _ErrorBanner(message);
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner(this.message);
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.errorContainer,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      message,
      style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer),
    ),
  );
}
