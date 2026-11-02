import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/map/riser_feature_hit.dart';

void main() {
  final authorized = [{'id': 81}, {'id': '82'}];

  test('resolves numeric property IDs and numeric Feature IDs', () {
    expect(resolveAuthorizedRiserId({'id': 81, 'properties': {'id': 81}}, authorized), 81);
    expect(resolveAuthorizedRiserId({'id': 82, 'properties': {}}, authorized), 82);
  });

  test('resolves string riser_id but rejects unknown and missing IDs', () {
    expect(resolveAuthorizedRiserId({'properties': {'riser_id': '82'}}, authorized), 82);
    expect(resolveAuthorizedRiserId({'properties': {'id': '999'}}, authorized), isNull);
    expect(resolveAuthorizedRiserId({'properties': {}}, authorized), isNull);
  });
}
