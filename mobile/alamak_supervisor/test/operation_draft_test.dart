import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/cases/operation_draft.dart';

void main() {
  test('normalizes Persian decimal input and validates each operation independently', () {
    final operation = OperationDraft(
      clientUuid: '11111111-1111-4111-8111-111111111111',
      categoryId: 4,
      materials: {
        12: MaterialDraft(id: 12, title: 'Paint', unit: 'L', quantity: '\u06f2\u066b\u0665'),
      },
    );
    expect(operation.validate(), isNull);
    expect(operation.materials.values.single.numericQuantity, 2.5);
  });

  test('uses real material ids and prevents duplicate material rows by map key', () {
    final operation = OperationDraft(clientUuid: '22222222-2222-4222-8222-222222222222', categoryId: 2);
    operation.materials[7] = MaterialDraft(id: 7, title: 'Cement', unit: 'kg', quantity: '1');
    operation.materials[7] = MaterialDraft(id: 7, title: 'Cement', unit: 'kg', quantity: '3');
    final payload = operation.toPayload();
    expect(operation.materials, hasLength(1));
    expect(payload['materials'], hasLength(1));
    expect((payload['materials'] as List).single['material_id'], 7);
    expect((payload['materials'] as List).single['qty'], '3');
  });
}
