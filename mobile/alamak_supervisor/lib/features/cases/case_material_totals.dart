List<Map<String, Object?>> aggregateCaseMaterials(Iterable<Map<String, dynamic>> operations) {
  final totals = <String, Map<String, Object?>>{};
  for (final operation in operations) {
    final materials = operation['materials'];
    if (materials is! List) continue;
    for (final raw in materials) {
      if (raw is! Map) continue;
      final material = Map<String, dynamic>.from(raw);
      final id = int.tryParse('${material['id']}');
      final unit = '${material['unit'] ?? ''}';
      final quantity = double.tryParse('${material['qty']}');
      if (id == null || quantity == null || !quantity.isFinite || quantity <= 0) continue;
      final key = '$id\u001f$unit';
      final row = totals.putIfAbsent(key, () => {
        'id': id,
        'title': material['title']?.toString() ?? '',
        'unit': unit,
        'qty': 0.0,
      });
      row['qty'] = (row['qty'] as double) + quantity;
    }
  }
  return totals.values.toList(growable: false);
}
