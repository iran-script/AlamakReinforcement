import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/cases/case_material_totals.dart';

void main() {
  test('aggregates quantities across operations by material id and compatible unit', () {
    final totals = aggregateCaseMaterials([
      {'materials': [
        {'id': 4, 'title': 'Paint', 'unit': 'L', 'qty': '1.25'},
        {'id': 9, 'title': 'Paint', 'unit': 'kg', 'qty': '2'},
      ]},
      {'materials': [
        {'id': 4, 'title': 'Paint', 'unit': 'L', 'qty': '0.75'},
        {'id': 9, 'title': 'Paint', 'unit': 'L', 'qty': '3'},
      ]},
    ]);
    expect(totals, hasLength(3));
    expect(totals.firstWhere((row) => row['id'] == 4 && row['unit'] == 'L')['qty'], '2');
    expect(totals.where((row) => row['id'] == 9).length, 2);
  });

  test('ignores invalid and nonpositive quantities', () {
    expect(aggregateCaseMaterials([{'materials': [
      {'id': 1, 'title': 'Cement', 'unit': 'kg', 'qty': '-2'},
      {'id': 2, 'title': 'Cement', 'unit': 'kg', 'qty': 'invalid'},
    ]}]), isEmpty);
  });

  test('adds decimal quantities without binary floating point drift', () {
    final totals = aggregateCaseMaterials([
      {'materials': [{'id': 1, 'title': 'Cable', 'unit': 'm', 'qty': '0.1'}]},
      {'materials': [{'id': 1, 'title': 'Cable', 'unit': 'm', 'qty': '0.2'}]},
    ]);
    expect(totals.single['qty'], '0.3');
  });

  test('ignores soft-deleted operations', () {
    final totals = aggregateCaseMaterials([
      {'deleted_at': '2026-01-01', 'materials': [{'id': 1, 'title': 'Cable', 'unit': 'm', 'qty': '10'}]},
      {'materials': [{'id': 1, 'title': 'Cable', 'unit': 'm', 'qty': '2'}]},
    ]);
    expect(totals.single['qty'], '2');
  });
}
