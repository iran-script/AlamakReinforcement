import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/map/zone_camera_bounds.dart';

void main() {
  test('computes bbox from a Polygon in EPSG:4326 coordinate order', () {
    final bounds = ZoneCameraBounds.fromGeoJson({
      'type': 'Polygon',
      'coordinates': [[[-1, 2], [4, 2], [4, 8], [-1, 8], [-1, 2]]],
    });
    expect(bounds?.west, -1);
    expect(bounds?.south, 2);
    expect(bounds?.east, 4);
    expect(bounds?.north, 8);
  });

  test('computes bbox across all parts of a MultiPolygon', () {
    final bounds = ZoneCameraBounds.fromGeoJson({
      'type': 'MultiPolygon',
      'coordinates': [
        [[[10, 20], [11, 20], [11, 21], [10, 20]]],
        [[[14, 18], [16, 18], [16, 22], [14, 18]]],
      ],
    });
    expect(bounds?.west, 10);
    expect(bounds?.south, 18);
    expect(bounds?.east, 16);
    expect(bounds?.north, 22);
  });

  test('rejects absent, invalid, and dateline-crossing bounds', () {
    expect(ZoneCameraBounds.fromGeoJson({'type': 'Polygon', 'coordinates': []}), isNull);
    expect(ZoneCameraBounds.fromGeoJson({'type': 'Point', 'coordinates': [0, 0]}), isNull);
    expect(ZoneCameraBounds.fromGeoJson({'type': 'Polygon', 'coordinates': [[[179, 1], [-179, 2]]]}), isNull);
  });
}
