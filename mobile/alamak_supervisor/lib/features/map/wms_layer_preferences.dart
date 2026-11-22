import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'wms_map_repository.dart';

class WmsLayerPreferences {
  const WmsLayerPreferences({this._storage = const FlutterSecureStorage()});

  final FlutterSecureStorage _storage;

  Future<Map<int, WmsViewSetting>> load(int userId) async {
    final raw = await _storage.read(key: _key(userId));
    if (raw == null) return {};
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return {};
      final result = <int, WmsViewSetting>{};
      for (final entry in decoded.entries) {
        final id = int.tryParse(entry.key.toString());
        final value = entry.value;
        if (id == null || value is! Map) continue;
        final opacity = (value['opacity'] as num?)?.toDouble();
        if (opacity == null || opacity.isNaN || opacity.isInfinite) continue;
        result[id] = WmsViewSetting(
          visible: value['visible'] == true,
          opacity: opacity.clamp(0, 1),
        );
      }
      return result;
    } catch (_) {
      return {};
    }
  }

  Future<void> save(
    int userId,
    Map<int, WmsViewSetting> settings,
    Set<int> activeLayerIds,
  ) async {
    final safeSettings = <String, Map<String, Object>>{};
    for (final id in activeLayerIds) {
      final setting = settings[id];
      if (setting == null) continue;
      safeSettings['$id'] = {
        'visible': setting.visible,
        'opacity': setting.opacity.clamp(0, 1),
      };
    }
    await _storage.write(key: _key(userId), value: jsonEncode(safeSettings));
  }

  static String _key(int userId) => 'wms_map_layer_preferences_v1_user_$userId';
}
