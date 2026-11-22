import 'dart:math' as math;

import 'package:maplibre_gl/maplibre_gl.dart';

import 'wms_map_repository.dart';

class WmsGetMapRequest {
  const WmsGetMapRequest({
    required this.bbox,
    required this.width,
    required this.height,
    required this.coordinates,
  });

  final String bbox;
  final int width;
  final int height;
  final LatLngQuad coordinates;
}

class WmsGetMapRequestBuilder {
  static const double _earthRadius = 6378137;
  static const double _maxLatitude = 85.0511287798066;

  static WmsGetMapRequest build({
    required WmsMapLayer layer,
    required LatLngBounds bounds,
    required int width,
    required int height,
  }) {
    final west = bounds.southwest.longitude;
    final south = bounds.southwest.latitude;
    final east = bounds.northeast.longitude;
    final north = bounds.northeast.latitude;
    if (![west, south, east, north].every((value) => value.isFinite) ||
        west >= east ||
        south >= north) {
      throw const FormatException('محدوده قابل مشاهده نقشه معتبر نیست.');
    }
    final bboxValues = layer.preferredCrs == 'EPSG:3857'
        ? _webMercatorBbox(west, south, east, north)
        : _geographicBbox(west, south, east, north, layer.version);
    final decimals = layer.preferredCrs == 'EPSG:3857' ? 2 : 8;
    final bbox = bboxValues
        .map((value) => value.toStringAsFixed(decimals))
        .join(',');

    return WmsGetMapRequest(
      bbox: bbox,
      width: width.clamp(64, 2048).toInt(),
      height: height.clamp(64, 2048).toInt(),
      coordinates: LatLngQuad(
        topLeft: LatLng(north, west),
        topRight: LatLng(north, east),
        bottomRight: LatLng(south, east),
        bottomLeft: LatLng(south, west),
      ),
    );
  }

  static List<double> _webMercatorBbox(
    double west,
    double south,
    double east,
    double north,
  ) {
    double x(double longitude) => _earthRadius * longitude * math.pi / 180;
    double y(double latitude) {
      final clamped = latitude.clamp(-_maxLatitude, _maxLatitude);
      final radians = clamped * math.pi / 180;
      return _earthRadius * math.log(math.tan(math.pi / 4 + radians / 2));
    }

    return [x(west), y(south), x(east), y(north)];
  }

  static List<double> _geographicBbox(
    double west,
    double south,
    double east,
    double north,
    String version,
  ) {
    return version == '1.3.0'
        ? [south, west, north, east]
        : [west, south, east, north];
  }
}
