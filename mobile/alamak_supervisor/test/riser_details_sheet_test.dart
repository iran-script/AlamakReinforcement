import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/map/riser_details_sheet.dart';

Map<String, dynamic> details({List<Map<String, dynamic>> cases = const []}) => {
  'riser': {'id': 41, 'r_giscode': 'GIS-41', 'status': 'open', 'latitude': 35.7, 'longitude': 51.4},
  'cases': cases,
  'operations': <Object>[] ,
};

Widget host(Widget child) => MaterialApp(home: Scaffold(body: child));

void main() {
  testWidgets('shows loading immediately and then actual riser details', (tester) async {
    final completer = Completer<Map<String, dynamic>>();
    await tester.pumpWidget(host(RiserDetailsSheet(riserId: 41, loadDetails: (_) => completer.future, onCreateCase: (_) {}, onOpenCase: (_) {})));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    completer.complete(details());
    await tester.pumpAndSettle();
    expect(find.text('GIS-41'), findsOneWidget);
    expect(find.textContaining('41'), findsWidgets);
  });

  testWidgets('shows Persian API error and retries on demand', (tester) async {
    var calls = 0;
    await tester.pumpWidget(host(RiserDetailsSheet(riserId: 41, loadDetails: (_) async {
      calls++;
      if (calls == 1) throw StateError('offline');
      return details();
    }, onCreateCase: (_) {}, onOpenCase: (_) {})));
    await tester.pumpAndSettle();
    expect(find.text('\u062a\u0644\u0627\u0634 \u0645\u062c\u062f\u062f'), findsOneWidget);
    await tester.tap(find.text('\u062a\u0644\u0627\u0634 \u0645\u062c\u062f\u062f'));
    await tester.pumpAndSettle();
    expect(calls, 2);
    expect(find.text('GIS-41'), findsOneWidget);
  });

  testWidgets('offers explicit active case selection and never auto-selects', (tester) async {
    int? opened;
    final rows = [
      {'id': 7, 'status': 'open', 'description': 'first'},
      {'id': 8, 'status': 'returned', 'description': 'second'},
    ];
    await tester.pumpWidget(host(RiserDetailsSheet(riserId: 41, loadDetails: (_) async => details(cases: rows), onCreateCase: (_) {}, onOpenCase: (id) => opened = id)));
    await tester.pumpAndSettle();
    expect(opened, isNull);
    expect(find.textContaining('پرونده قابل ادامه'), findsOneWidget);
    expect(find.textContaining('پرونده 7'), findsOneWidget);
    expect(find.textContaining('پرونده 8'), findsOneWidget);
    await tester.tap(find.textContaining('پرونده 8'));
    await tester.pumpAndSettle();
    expect(opened, 8);
  });

  testWidgets('local pending report is marked and cannot create a duplicate report', (tester) async {
    await tester.pumpWidget(host(RiserDetailsSheet(
      riserId: 41, syncPending: true, loadDetails: (_) async => details(),
      onCreateCase: (_) {}, onOpenCase: (_) {},
    )));
    await tester.pumpAndSettle();
    expect(find.text('\u062b\u0628\u062a \u0645\u062d\u0644\u06cc \u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc'), findsOneWidget);
    expect(find.text('\u062b\u0628\u062a \u0646\u06cc\u0627\u0632 \u0628\u0647 \u062a\u0639\u0645\u06cc\u0631'), findsNothing);
  });

  testWidgets('ignores a late API result after the sheet is disposed', (tester) async {
    final completer = Completer<Map<String, dynamic>>();
    await tester.pumpWidget(host(RiserDetailsSheet(riserId: 41, loadDetails: (_) => completer.future, onCreateCase: (_) {}, onOpenCase: (_) {})));
    await tester.pumpWidget(host(const SizedBox.shrink()));
    completer.complete(details());
    await tester.pump();
    expect(tester.takeException(), isNull);
  });

  testWidgets('does not issue another details request when the sheet rebuilds', (tester) async {
    var calls = 0;
    final sheet = RiserDetailsSheet(riserId: 41, loadDetails: (_) async { calls++; return details(); }, onCreateCase: (_) {}, onOpenCase: (_) {});
    await tester.pumpWidget(host(sheet));
    await tester.pumpAndSettle();
    await tester.pumpWidget(host(sheet));
    await tester.pumpAndSettle();
    expect(calls, 1);
  });
}
