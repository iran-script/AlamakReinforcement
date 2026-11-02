import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/cases/material_picker_sheet.dart';

const _references = <String, dynamic>{
  'materials': [
    {
      'id': 11,
      'title': 'میلگرد یکتا',
      'code': 'M-11',
      'unit': 'متر',
      'material_category_id': 4,
      'category': {'id': 4, 'title': 'فلزات'},
    },
    {
      'id': 12,
      'title': 'شیر فلکه',
      'code': 'V-12',
      'unit': 'عدد',
      'material_category_id': 5,
      'category': {'id': 5, 'title': 'تجهیزات'},
    },
    {
      'id': 13,
      'title': 'بست فلزی',
      'code': 'M-13',
      'unit': 'عدد',
      'material_category_id': 4,
      'category': {'id': 4, 'title': 'فلزات'},
    },
  ],
};

class _PickerHarness extends StatefulWidget {
  const _PickerHarness({this.initial = const {}, this.failFirst = false});
  final Map<int, String> initial;
  final bool failFirst;

  @override
  State<_PickerHarness> createState() => _PickerHarnessState();
}

class _PickerHarnessState extends State<_PickerHarness> {
  Map<int, String>? result;
  int calls = 0;

  Future<Map<String, dynamic>> load() async {
    calls++;
    if (widget.failFirst && calls == 1) throw StateError('offline');
    return _references;
  }

  @override
  Widget build(BuildContext context) => Directionality(
    textDirection: TextDirection.rtl,
    child: Scaffold(
      body: Center(
        child: ElevatedButton(
          key: const Key('launch-picker'),
          onPressed: () async {
            result = await showMaterialPicker(
              context,
              loadReferences: load,
              initialQuantities: widget.initial,
            );
            setState(() {});
          },
          child: const Text('بازکردن'),
        ),
      ),
    ),
  );
}

void main() {
  Future<_PickerHarnessState> open(
    WidgetTester tester, {
    _PickerHarness? harness,
  }) async {
    await tester.pumpWidget(
      MaterialApp(home: harness ?? const _PickerHarness()),
    );
    await tester.tap(find.byKey(const Key('launch-picker')));
    await tester.pumpAndSettle();
    return tester.state(find.byType(_PickerHarness));
  }

  testWidgets('search normalizes Arabic Yeh and searches the material code', (
    tester,
  ) async {
    await open(tester);
    await tester.enterText(find.byKey(const Key('material-search')), 'ميلگرد');
    await tester.pump();
    expect(find.byKey(const Key('material-option-11')), findsOneWidget);
    expect(find.byKey(const Key('material-option-12')), findsNothing);
    await tester.enterText(find.byKey(const Key('material-search')), 'V-12');
    await tester.pump();
    expect(find.byKey(const Key('material-option-12')), findsOneWidget);
  });

  testWidgets('filters using categories returned by references', (
    tester,
  ) async {
    await open(tester);
    await tester.tap(find.widgetWithText(ChoiceChip, 'فلزات'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('material-option-11')), findsOneWidget);
    expect(find.byKey(const Key('material-option-12')), findsNothing);
    expect(find.byKey(const Key('material-option-13')), findsOneWidget);
  });

  testWidgets('selects multiple materials and confirms their quantities', (
    tester,
  ) async {
    final state = await open(tester);
    await tester.tap(find.byKey(const Key('material-option-11')));
    await tester.pump();
    await tester.tap(find.byKey(const Key('material-option-12')));
    await tester.pump();
    await tester.enterText(
      find.byKey(const Key('material-quantity-11')),
      '2.5',
    );
    await tester.enterText(find.byKey(const Key('material-quantity-12')), '3');
    await tester.tap(find.byKey(const Key('material-confirm')));
    await tester.pumpAndSettle();
    expect(state.result, {11: '2.5', 12: '3'});
  });

  testWidgets('edit starts with prior selection and quantity preserved', (
    tester,
  ) async {
    final state = await open(
      tester,
      harness: const _PickerHarness(initial: {12: '7'}),
    );
    expect(find.byKey(const Key('material-quantity-12')), findsOneWidget);
    expect(
      tester
          .widget<TextField>(find.byKey(const Key('material-quantity-12')))
          .controller!
          .text,
      '7',
    );
    await tester.enterText(find.byKey(const Key('material-quantity-12')), '8');
    await tester.tap(find.byKey(const Key('material-confirm')));
    await tester.pumpAndSettle();
    expect(state.result, {12: '8'});
  });

  testWidgets('cancel does not return temporary selection', (tester) async {
    final state = await open(tester);
    await tester.tap(find.byKey(const Key('material-option-11')));
    await tester.pump();
    await tester.tapAt(const Offset(2, 2));
    await tester.pumpAndSettle();
    expect(state.result, isNull);
  });

  testWidgets(
    'invalid quantities are rejected and retry recovers from API error',
    (tester) async {
      await open(tester, harness: const _PickerHarness(failFirst: true));
      expect(find.text('دریافت فهرست مصالح ناموفق بود.'), findsOneWidget);
      await tester.tap(find.text('تلاش دوباره'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('material-option-11')));
      await tester.pump();
      await tester.tap(find.byKey(const Key('material-confirm')));
      await tester.pump();
      expect(
        find.text(
          'برای هر مصالح انتخاب‌شده، مقدار معتبر و بیشتر از صفر وارد کنید.',
        ),
        findsOneWidget,
      );
    },
  );
}
