import 'package:flutter_test/flutter_test.dart';
import 'package:maplibre_gl/maplibre_gl.dart';
import 'package:alamak_supervisor/features/map/wms_getmap_request.dart';
import 'package:alamak_supervisor/features/map/wms_map_repository.dart';

void main() {
  final bounds = LatLngBounds(
    southwest: LatLng(34.5, 49.5),
    northeast: LatLng(34.6, 49.6),
  );

  WmsMapLayer layer({required String crs, required String version}) =>
      WmsMapLayer(
        id: 7,
        serviceId: 2,
        serviceName: 'سرویس آزمایشی',
        title: 'لایه زون',
        version: version,
        layerName: 'zones:zone',
        proxyUrl: '/api/v1/supervisor/map/wms/2/7',
        preferredCrs: crs,
        opacity: 1,
        defaultVisible: false,
        sortOrder: 0,
      );

  test('Web Mercator BBOX is projected from geographic map bounds', () {
    final request = WmsGetMapRequestBuilder.build(
      layer: layer(crs: 'EPSG:3857', version: '1.3.0'),
      bounds: bounds,
      width: 1024,
      height: 768,
    );
    final values = request.bbox.split(',').map(double.parse).toList();
    expect(values, hasLength(4));
    expect(values[0], closeTo(5510315, 1));
    expect(values[1], closeTo(4096139, 1));
    expect(values[2], greaterThan(values[0]));
    expect(values[3], greaterThan(values[1]));
    expect(request.width, 1024);
    expect(request.height, 768);
  });

  test('WMS 1.3 EPSG:4326 BBOX uses latitude then longitude axis order', () {
    final request = WmsGetMapRequestBuilder.build(
      layer: layer(crs: 'EPSG:4326', version: '1.3.0'),
      bounds: bounds,
      width: 512,
      height: 512,
    );
    expect(request.bbox, '34.50000000,49.50000000,34.60000000,49.60000000');
  });

  test('WMS 1.1.1 EPSG:4326 BBOX uses longitude then latitude axis order', () {
    final request = WmsGetMapRequestBuilder.build(
      layer: layer(crs: 'EPSG:4326', version: '1.1.1'),
      bounds: bounds,
      width: 512,
      height: 512,
    );
    expect(request.bbox, '49.50000000,34.50000000,49.60000000,34.60000000');
  });

  test('catalog parser keeps stable IDs and the server proxy URL', () {
    final parsed = WmsMapLayer.parseCatalog({
      'success': true,
      'services': [
        {
          'id': 2,
          'name': 'سرویس',
          'version': '1.3.0',
          'layers': [
            {
              'id': 7,
              'title': 'زون‌ها',
              'layer_name': 'zones:zone',
              'url': '/api/v1/supervisor/map/wms/2/7',
              'preferred_crs': 'EPSG:3857',
              'opacity': .7,
              'default_visible': true,
              'sort_order': 3,
            },
          ],
        },
      ],
    });
    expect(parsed, hasLength(1));
    expect(parsed.single.id, 7);
    expect(parsed.single.proxyUrl, '/api/v1/supervisor/map/wms/2/7');
    expect(parsed.single.storageId, '2:7');
    expect(parsed.single.opacity, .7);
  });
}
