class ZoneCameraBounds {
  const ZoneCameraBounds(this.west, this.south, this.east, this.north);
  final double west;
  final double south;
  final double east;
  final double north;

  static ZoneCameraBounds? fromGeoJson(Map<String, dynamic> geometry) {
    final type = geometry['type'];
    if (type != 'Polygon' && type != 'MultiPolygon') return null;
    final coordinates = geometry['coordinates'];
    if (coordinates is! List) return null;
    var west = double.infinity, south = double.infinity;
    var east = double.negativeInfinity, north = double.negativeInfinity;
    void visit(Object? value) {
      if (value is List && value.length >= 2 && value[0] is num && value[1] is num) {
        final lon = (value[0] as num).toDouble();
        final lat = (value[1] as num).toDouble();
        if (!lon.isFinite || !lat.isFinite || lon < -180 || lon > 180 || lat < -90 || lat > 90) return;
        if (lon < west) west = lon;
        if (lon > east) east = lon;
        if (lat < south) south = lat;
        if (lat > north) north = lat;
        return;
      }
      if (value is List) for (final child in value) { visit(child); }
    }
    visit(coordinates);
    if (!west.isFinite || !south.isFinite || !east.isFinite || !north.isFinite || west >= east || south >= north) return null;
    // Crossing the antimeridian cannot be safely represented by a single bounds fit.
    if (east - west > 180) return null;
    return ZoneCameraBounds(west, south, east, north);
  }

  @override
  String toString() => '[$west, $south, $east, $north]';
}
