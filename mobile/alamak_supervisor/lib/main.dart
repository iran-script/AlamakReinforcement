import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'features/auth/session_controller.dart';
import 'features/auth/session_pages.dart';
import 'features/cases/case_pages.dart';
import 'features/cases/repair_case_page.dart';
import 'features/shell/supervisor_shell.dart';

void main() => runApp(const ProviderScope(child: AlamakSupervisorApp()));

class AlamakSupervisorApp extends ConsumerStatefulWidget {
  const AlamakSupervisorApp({super.key});

  @override
  ConsumerState<AlamakSupervisorApp> createState() => _AlamakSupervisorAppState();
}

class _AlamakSupervisorAppState extends ConsumerState<AlamakSupervisorApp> {
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    final session = ref.read(sessionProvider);
    _router = GoRouter(
      initialLocation: '/loading',
      refreshListenable: session,
      redirect: (context, state) {
        final path = state.uri.path;
        if (!session.initialized) return path == '/loading' ? null : '/loading';
        if (session.serverUrl == null) return path == '/server' ? null : '/server';
        if (session.token == null) return path == '/login' || path == '/server' ? null : '/login';
        if (path == '/loading' || path == '/server' || path == '/login') return '/app/home';
        return null;
      },
      routes: [
        GoRoute(path: '/loading', builder: (_, _) => const LoadingPage()),
        GoRoute(path: '/server', builder: (_, _) => const ServerSetupPage()),
        GoRoute(path: '/login', builder: (_, _) => const SupervisorLoginPage()),
        GoRoute(path: '/app/:tab', builder: (_, state) => SupervisorShell(tab: state.pathParameters['tab'] ?? 'home')),
        GoRoute(path: '/case/new/:riserId', builder: (_, state) => NewCasePage(riserId: int.parse(state.pathParameters['riserId']!), returnToMap: true)),
        GoRoute(path: '/case/:caseId', builder: (_, state) => RepairCasePage(caseId: int.parse(state.pathParameters['caseId']!))),
      ],
    );
    session.initialize();
  }

  @override
  Widget build(BuildContext context) => MaterialApp.router(
        title: 'سامانه ناظر تعمیرات علمک‌ها',
        debugShowCheckedModeBanner: false,
        locale: const Locale('fa'),
        supportedLocales: const [Locale('fa'), Locale('en')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        theme: ThemeData(
          useMaterial3: true,
          colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xff145c78), brightness: Brightness.light),
          scaffoldBackgroundColor: const Color(0xfff4f7f9),
          cardTheme: CardThemeData(elevation: 0, margin: EdgeInsets.zero, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18))),
          inputDecorationTheme: InputDecorationTheme(
            filled: true, fillColor: Colors.white,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
          ),
        ),
        routerConfig: _router,
      );
}
