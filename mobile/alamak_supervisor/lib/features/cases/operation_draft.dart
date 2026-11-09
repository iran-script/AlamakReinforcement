import 'package:uuid/uuid.dart';

String normalizeIdentifier(Object? value) => '${value ?? ''}'.trim();

String normalizeAmount(String value) {
  const digits = '\u06f0\u06f1\u06f2\u06f3\u06f4\u06f5\u06f6\u06f7\u06f8\u06f9\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669';
  var result = value.trim();
  for (var i = 0; i < 10; i++) {
    result = result.replaceAll(digits[i], '$i');
    result = result.replaceAll(digits[i + 10], '$i');
  }
  return result.replaceAll('\u066b', '.').replaceAll('\u066c', '');
}

class MaterialDraft {
  MaterialDraft({required this.id, required this.title, required this.unit, required this.quantity});

  final int id;
  final String title;
  final String unit;
  String quantity;

  double? get numericQuantity => double.tryParse(normalizeAmount(quantity));

  Map<String, dynamic> toPayload() => {'material_id': id, 'qty': normalizeAmount(quantity)};
}

class OperationDraft {
  OperationDraft({
    this.id,
    required this.clientUuid,
    this.categoryId,
    this.description = '',
    DateTime? date,
    this.status = 'draft',
    this.version,
    Map<int, MaterialDraft>? materials,
  })  : date = date ?? DateTime.now(),
        materials = materials ?? {};

  factory OperationDraft.fromJson(Map<String, dynamic> json) {
    final materials = <int, MaterialDraft>{};
    for (final raw in (json['materials'] as List? ?? const [])) {
      final material = Map<String, dynamic>.from(raw as Map);
      final id = int.tryParse(normalizeIdentifier(material['id'] ?? material['material_id']));
      if (id == null) continue;
      materials[id] = MaterialDraft(id: id, title: '${material['title'] ?? 'Material #$id'}', unit: '${material['unit'] ?? ''}', quantity: '${material['qty'] ?? ''}');
    }
    return OperationDraft(
      id: int.tryParse(normalizeIdentifier(json['id'])),
      clientUuid: '${json['client_uuid'] ?? const Uuid().v4()}',
      categoryId: int.tryParse(normalizeIdentifier(json['operation_category_id'] ?? (json['operation_category'] as Map?)?['id'])),
      description: '${json['description'] ?? ''}',
      date: DateTime.tryParse('${json['operation_date'] ?? json['date'] ?? ''}'),
      status: '${json['status'] ?? 'draft'}',
      version: json['updated_at']?.toString(),
      materials: materials,
    );
  }

  final int? id;
  final String clientUuid;
  int? categoryId;
  String description;
  DateTime date;
  String status;
  String? version;
  final Map<int, MaterialDraft> materials;

  bool get isPersisted => id != null;

  String? validate() {
    if (categoryId == null) return '\u0646\u0648\u0639 \u0639\u0645\u0644\u06cc\u0627\u062a \u0631\u0627 \u0627\u0646\u062a\u062e\u0627\u0628 \u06a9\u0646\u06cc\u062f.';
    if (materials.values.any((material) => material.numericQuantity == null || material.numericQuantity! <= 0)) return '\u0645\u0642\u062f\u0627\u0631 \u0647\u0645\u0647 \u0645\u0635\u0627\u0644\u062d \u0628\u0627\u06cc\u062f \u0628\u0632\u0631\u06af\u200c\u062a\u0631 \u0627\u0632 \u0635\u0641\u0631 \u0628\u0627\u0634\u062f.';
    return null;
  }

  Map<String, dynamic> toPayload() => {
        if (id != null) 'id': id,
        'client_uuid': clientUuid,
        if (version != null) 'version': version,
        'operation_category_id': categoryId,
        'operation_date': date.toIso8601String().split('T').first,
        'description': description.trim(),
        'materials': materials.values.map((material) => material.toPayload()).toList(),
      };
}
