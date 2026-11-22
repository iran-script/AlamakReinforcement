import 'package:alamak_supervisor/features/settings/about_page.dart';
import 'package:alamak_supervisor/features/auth/session_pages.dart';
import 'package:alamak_supervisor/features/auth/startup_coordinator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('about page displays the requested developer details', (
    tester,
  ) async {
    await tester.pumpWidget(const MaterialApp(home: AboutPage()));

    expect(find.text('سامانه مدیریت علمک‌ها'), findsOneWidget);
    expect(find.text('برنامه‌نویس'), findsOneWidget);
    expect(find.text('سعید ذوالفعلی'), findsOneWidget);
    expect(find.text('09397311933'), findsOneWidget);
    expect(find.text('نسخه 1.0.0 (1)'), findsOneWidget);
  });

  testWidgets('startup loading screen uses the custom identity', (
    tester,
  ) async {
    final startup = StartupCoordinator(initialize: _noop);
    await tester.pumpWidget(
      MaterialApp(
        home: LoadingPage(startup: startup),
      ),
    );

    expect(find.text('سامانه مدیریت علمک‌ها'), findsOneWidget);
    expect(find.text('سعید ذوالفعلی'), findsOneWidget);
    expect(find.text('برنامه‌نویس: طراحی و توسعه نرم‌افزار'), findsOneWidget);
    expect(find.text('09397311933'), findsOneWidget);
  });
}

Future<void> _noop() async {}
