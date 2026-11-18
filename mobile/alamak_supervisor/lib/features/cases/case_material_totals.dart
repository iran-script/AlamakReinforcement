import 'operation_draft.dart';

String _normaliseDecimal(Object? value) {
  var text = normalizeAmount('${value ?? ''}').trim();
  if (text.startsWith('+')) text = text.substring(1);
  if (text.startsWith('.')) text = '0$text';
  if (text.startsWith('-.')) text = '-0${text.substring(1)}';
  return text;
}

String _addDecimals(String left, String right) {
  String splitAndPad(String value, int scale) {
    final parts = value.split('.');
    return parts.first + (parts.length == 1 ? '' : parts[1]).padRight(scale, '0');
  }

  final leftParts = left.split('.');
  final rightParts = right.split('.');
  final scale = [
    leftParts.length == 1 ? 0 : leftParts[1].length,
    rightParts.length == 1 ? 0 : rightParts[1].length,
  ].reduce((a, b) => a > b ? a : b);
  final leftInteger = BigInt.parse(splitAndPad(left, scale));
  final rightInteger = BigInt.parse(splitAndPad(right, scale));
  final total = leftInteger + rightInteger;
  final negative = total.isNegative;
  final digits = total.abs().toString().padLeft(scale + 1, '0');
  final whole = scale == 0 ? digits : digits.substring(0, digits.length - scale);
  var fraction = scale == 0 ? '' : digits.substring(digits.length - scale).replaceFirst(RegExp(r'0+$'), '');
  return '${negative ? '-' : ''}$whole${fraction.isEmpty ? '' : '.$fraction'}';
}

List<Map<String, Object?>> aggregateCaseMaterials(Iterable<Map<String, dynamic>> operations) {
  final totals = <String, Map<String, Object?>>{};
  for (final operation in operations) {
    if (operation['deleted_at'] != null) continue;
    final materials = operation['materials'];
    if (materials is! List) continue;
    for (final raw in materials) {
      if (raw is! Map) continue;
      final material = Map<String, dynamic>.from(raw);
      final id = int.tryParse('${material['id'] ?? material['material_id']}');
      final unit = '${material['unit'] ?? ''}';
      final quantity = _normaliseDecimal(material['qty']);
      final quantityNumber = double.tryParse(quantity);
      if (id == null || quantityNumber == null || !quantityNumber.isFinite || quantityNumber <= 0) continue;
      final key = '$id\u001f$unit';
      final row = totals.putIfAbsent(key, () => {
        'id': id,
        'title': material['title']?.toString() ?? '',
        'unit': unit,
        'qty': '0',
      });
      row['qty'] = _addDecimals('${row['qty']}', quantity);
    }
  }
  return totals.values.toList(growable: false);
}
