import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../core/data/api_client.dart';

class WmsMapLayer {
  const WmsMapLayer({
    required this.id,
    required this.serviceId,
    required this.serviceName,
    required this.title,
    required this.version,
    required this.layerName,
    required this.proxyUrl,
    required this.preferredCrs,
    required this.opacity,
    required this.defaultVisible,
    required this.sortOrder,
  });

  final int id;
  final int serviceId;
  final String serviceName;
  final String title;
  final String version;
  final String layerName;
  final String proxyUrl;
  final String preferredCrs;
  final double opacity;
  final bool defaultVisible;
  final int sortOrder;

  String get storageId => '$serviceId:$id';

  static List<WmsMapLayer> parseCatalog(Object? value) {
    if (value is! Map ||
        value['success'] != true ||
        value['services'] is! List) {
      throw const FormatException('فهرست لایه‌های مجاز نقشه معتبر نیست.');
    }
    final layers = <WmsMapLayer>[];
    for (final serviceValue in value['services'] as List) {
      if (serviceValue is! Map || serviceValue['layers'] is! List) {
        continue;
      }
      final serviceId = int.tryParse('${serviceValue['id']}');
      final serviceName = serviceValue['name']?.toString() ?? '';
      final version = serviceValue['version']?.toString() ?? '1.3.0';
      if (serviceId == null) continue;
      for (final layerValue in serviceValue['layers'] as List) {
        if (layerValue is! Map) continue;
        final id = int.tryParse('${layerValue['id']}');
        final proxyUrl = layerValue['url']?.toString() ?? '';
        final crs = layerValue['preferred_crs']?.toString() ?? '';
        if (id == null ||
            proxyUrl.isEmpty ||
            !const ['EPSG:3857', 'EPSG:4326'].contains(crs)) {
          continue;
        }
        layers.add(
          WmsMapLayer(
            id: id,
            serviceId: serviceId,
            serviceName: serviceName,
            title: layerValue['title']?.toString().trim().isNotEmpty == true
                ? layerValue['title'].toString()
                : (layerValue['layer_name']?.toString() ?? 'لایه نقشه'),
            version: version,
            layerName: layerValue['layer_name']?.toString() ?? '',
            proxyUrl: proxyUrl,
            preferredCrs: crs,
            opacity: ((layerValue['opacity'] as num?)?.toDouble() ?? 1).clamp(
              0,
              1,
            ),
            defaultVisible: layerValue['default_visible'] == true,
            sortOrder: (layerValue['sort_order'] as num?)?.toInt() ?? id,
          ),
        );
      }
    }
    layers.sort((a, b) {
      final service = a.serviceName.compareTo(b.serviceName);
      return service != 0 ? service : a.sortOrder.compareTo(b.sortOrder);
    });
    return layers;
  }
}

class WmsViewSetting {
  const WmsViewSetting({required this.visible, required this.opacity});

  final bool visible;
  final double opacity;

  WmsViewSetting copyWith({bool? visible, double? opacity}) => WmsViewSetting(
    visible: visible ?? this.visible,
    opacity: opacity ?? this.opacity,
  );
}

class WmsLayerStatus {
  const WmsLayerStatus({this.loading = false, this.message});

  final bool loading;
  final String? message;
}

class WmsMapRepository {
  const WmsMapRepository();

  Future<List<WmsMapLayer>> loadLayers(ApiClient api) async {
    final response = await api.dio.get(
      'v1/supervisor/map/wms-layers',
      options: Options(headers: const {'Cache-Control': 'no-cache'}),
    );
    return WmsMapLayer.parseCatalog(response.data);
  }

  Future<Uint8List> loadImage({
    required ApiClient api,
    required String serverUrl,
    required WmsMapLayer layer,
    required String bbox,
    required int width,
    required int height,
  }) async {
    final base = Uri.parse('${serverUrl.replaceFirst(RegExp(r'/+$'), '')}/');
    final endpoint = base.resolve(layer.proxyUrl);
    final uri = endpoint.replace(
      queryParameters: {'BBOX': bbox, 'WIDTH': '$width', 'HEIGHT': '$height'},
    );
    final response = await api.dio.getUri<List<int>>(
      uri,
      options: Options(
        responseType: ResponseType.bytes,
        receiveTimeout: const Duration(seconds: 25),
        followRedirects: false,
        maxRedirects: 0,
        headers: const {'Accept': 'image/png'},
      ),
    );
    final contentType = (response.headers.value('content-type') ?? '')
        .toLowerCase();
    if (response.statusCode != 200 ||
        !contentType.startsWith('image/png') ||
        response.data == null) {
      throw const FormatException('تصویر لایه نقشه معتبر دریافت نشد.');
    }
    final bytes = Uint8List.fromList(response.data!);
    const pngSignature = [137, 80, 78, 71, 13, 10, 26, 10];
    if (bytes.length < pngSignature.length ||
        !List<int>.generate(pngSignature.length, (index) => bytes[index])
            .asMap()
            .entries
            .every((entry) => entry.value == pngSignature[entry.key])) {
      throw const FormatException('تصویر لایه نقشه قالب PNG معتبر ندارد.');
    }
    return bytes;
  }
}
