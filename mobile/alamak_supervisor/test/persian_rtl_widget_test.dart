import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Persian interface text renders in right-to-left direction', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      locale: Locale('fa'),
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: Scaffold(
          body: Center(child: Text('ناظر تعمیرات علمک‌ها')),
        ),
      ),
    ));

    expect(find.text('ناظر تعمیرات علمک‌ها'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('ناظر تعمیرات علمک‌ها'))),
        TextDirection.rtl);
  });
}
