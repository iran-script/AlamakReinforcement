int? resolveAuthorizedRiserId(Map<String, dynamic> feature, Iterable<Map<String, dynamic>> authorizedRisers) {
  final properties = Map<String, dynamic>.from(feature['properties'] as Map? ?? const {});
  final rawId = properties['riser_id'] ?? properties['id'] ?? feature['id'];
  final id = rawId is num ? rawId.toInt() : int.tryParse('$rawId');
  if (id == null) return null;
  return authorizedRisers.any((riser) => int.tryParse('${riser['id']}') == id) ? id : null;
}
