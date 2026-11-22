import 'dart:convert';
import 'dart:math';
import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maplibre_gl/maplibre_gl.dart';

import '../auth/session_controller.dart';
import '../cases/case_repository.dart';
import 'riser_details_sheet.dart';
import 'riser_feature_hit.dart';
import 'zone_camera_bounds.dart';
import 'wms_getmap_request.dart';
import 'wms_layer_preferences.dart';
import 'wms_layers_sheet.dart';
import 'wms_map_repository.dart';

class MapPage extends ConsumerStatefulWidget {
  const MapPage({super.key});
  @override
  ConsumerState<MapPage> createState() => _MapPageState();
}

class _MapPageState extends ConsumerState<MapPage> {
  final GlobalKey _mapWidgetKey = GlobalKey();
  final WmsMapRepository _wmsRepository = const WmsMapRepository();
  final WmsLayerPreferences _wmsPreferences = const WmsLayerPreferences();
  MapLibreMapController? _map;
  Map<String, dynamic>? _profile;
  List<Map<String, dynamic>> _risers = [];
  final Set<int> _pendingRiserIds = {};
  bool _loading = true;
  String? _error;
  String _search = '';
  String? _status;
  bool _sourceReady = false;
  bool _selectingRiser = false;
  bool _featureQueryInFlight = false;
  bool _zoneFitDone = false;
  bool _isSuperadmin = false;
  String? _zoneMessage;
  bool _loadingRisers = false;
  bool _reloadAfterRequest = false;
  bool _hasMore = false;
  int _nextPage = 1;
  Map<String, dynamic>? _visibleQuery;
  Timer? _cameraDebounce;
  List<WmsMapLayer> _wmsLayers = [];
  Map<int, WmsViewSetting> _wmsSettings = {};
  final Map<int, WmsLayerStatus> _wmsStatuses = {};
  final Map<int, String> _lastWmsRequestKeys = {};
  final Set<int> _wmsImageSources = {};
  bool _wmsCatalogLoading = false;
  bool _wmsRefreshInFlight = false;
  bool _wmsRefreshPending = false;
  String? _wmsCatalogError;

  @override
  void initState() {
    super.initState();
    _loadMap();
  }

  @override
  void dispose() {
    _cameraDebounce?.cancel();
    super.dispose();
  }

  Future<void> _loadMap() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final session = ref.read(sessionProvider);
    try {
      final profile = await session.loadMapProfile();
      if (profile == null) throw StateError('تنظیمات نقشه در دسترس نیست.');
      if (!mounted) return;
      setState(() {
        _profile = profile;
        _loading = false;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = exception is DioException
            ? 'بارگذاری نقشه ناموفق بود. اتصال سرور را بررسی کنید.'
            : exception.toString();
      });
    }
  }

  String _rasterStyle(String tileUrl) {
    final server = ref.read(sessionProvider).serverUrl!;
    final resolved = tileUrl.startsWith('/')
        ? Uri.parse(server).resolve(tileUrl).toString()
        : tileUrl;
    final minZoom = (_profile?['min_zoom'] as num?)?.toInt() ?? 0;
    final maxZoom = (_profile?['max_zoom'] as num?)?.toInt() ?? 19;
    return jsonEncode({
      'version': 8,
      'name': _profile?['name'] ?? 'Alamak Internal Raster',
      'sources': {
        'alamak-base': {
          'type': 'raster',
          'tiles': [resolved],
          'tileSize': 256,
          'minzoom': minZoom,
          'maxzoom': maxZoom,
          'attribution': _profile?['attribution'] ?? '',
        },
      },
      'layers': [
        {
          'id': 'background',
          'type': 'background',
          'paint': {'background-color': '#e8edf0'},
        },
        {
          'id': 'alamak-base-layer',
          'type': 'raster',
          'source': 'alamak-base',
          'minzoom': minZoom,
          'maxzoom': maxZoom,
        },
      ],
    });
  }

  Map<String, dynamic> get _geoJson => {
    'type': 'FeatureCollection',
    'features': _risers
        .map(
          (riser) => {
            'type': 'Feature',
            'id': int.tryParse('${riser['id']}') ?? riser['id'],
            'geometry': {
              'type': 'Point',
              'coordinates': [riser['longitude'], riser['latitude']],
            },
            'properties': {
              'id': int.tryParse('${riser['id']}') ?? riser['id'],
              'riser_id': int.tryParse('${riser['id']}') ?? riser['id'],
              'giscode': riser['r_giscode'] ?? riser['giscode'] ?? '',
              'status':
                  riser['maintenance_status'] ?? riser['status'] ?? 'default',
              'sync_pending': _pendingRiserIds.contains(
                int.tryParse('${riser['id']}'),
              ),
            },
          },
        )
        .toList(),
  };

  Future<void> _onMapCreated(MapLibreMapController controller) async {
    _map = controller;
    _sourceReady = false;
  }

  Future<void> _onStyleLoaded() async {
    final map = _map;
    if (map == null) return;
    try {
      await map.addGeoJsonSource(
        'authorized-risers',
        _geoJson,
        promoteId: 'id',
      );
      await map.addCircleLayer(
        'authorized-risers',
        'authorized-riser-circles',
        CircleLayerProperties(
          circleRadius: [
            'interpolate',
            ['linear'],
            ['zoom'],
            5,
            4,
            15,
            8,
          ],
          circleColor: [
            'case',
            [
              '==',
              ['get', 'sync_pending'],
              true,
            ],
            '#f59e0b',
            [
              'match',
              ['get', 'status'],
              'open',
              '#d97706',
              'in_progress',
              '#2563eb',
              'submitted',
              '#7c3aed',
              'returned',
              '#f97316',
              'completed',
              '#059669',
              'flagRiser',
              '#d97706',
              'submitOperation',
              '#7c3aed',
              'resolveLead',
              '#2563eb',
              'error',
              '#dc2626',
              '#607d8b',
            ],
          ],
          circleStrokeColor: '#ffffff',
          circleStrokeWidth: 2,
        ),
      );
      await map.addSymbolLayer(
        'authorized-risers',
        'riser-labels',
        SymbolLayerProperties(
          textField: '{giscode}',
          textSize: 10,
          textColor: '#17212b',
          textHaloColor: '#ffffff',
          textHaloWidth: 1.2,
          textOffset: const [0, 1.1],
          textAnchor: 'top',
        ),
        minzoom: 14,
      );
      await map.addSymbolLayer(
        'authorized-risers',
        'riser-returned-markers',
        SymbolLayerProperties(
          textField: [
            'match',
            ['get', 'status'],
            'returned',
            '↻',
            '',
          ],
          textSize: 12,
          textColor: '#7c2d12',
          textHaloColor: '#ffffff',
          textHaloWidth: 1,
        ),
      );
      _sourceReady = true;
      await _fitInitialZone();
      await _loadVisibleRisers();
      await _loadWmsLayers();
    } catch (exception) {
      if (mounted) setState(() => _error = 'بارگذاری علمک‌ها ناموفق بود.');
    }
  }

  Future<void> _fitInitialZone() async {
    if (_zoneFitDone || _map == null) return;
    _zoneFitDone = true;
    try {
      final response = await ref
          .read(sessionProvider)
          .api!
          .dio
          .get('v1/mobile/map/zone');
      final data = Map<String, dynamic>.from(response.data as Map);
      _isSuperadmin = data['is_superadmin'] == true;
      if (_isSuperadmin) {
        debugPrint(
          '[map] superadmin has global map access; no zone fit applied',
        );
        return;
      }
      final geometry = data['geometry'];
      final bounds = geometry is Map
          ? ZoneCameraBounds.fromGeoJson(Map<String, dynamic>.from(geometry))
          : null;
      if (bounds == null) {
        _zoneMessage =
            'محدوده معتبر برای این حساب دریافت نشد؛ نمای پیش‌فرض نقشه نمایش داده می‌شود.';
        debugPrint('[map] zone geometry missing or invalid');
        if (mounted) setState(() {});
        return;
      }
      debugPrint(
        '[map] fitting zone id=${data['zone_id']} srid=${data['srid']} bbox=$bounds',
      );
      await _map!.animateCamera(
        CameraUpdate.newLatLngBounds(
          LatLngBounds(
            southwest: LatLng(bounds.south, bounds.west),
            northeast: LatLng(bounds.north, bounds.east),
          ),
          left: 48,
          top: 110,
          right: 48,
          bottom: 72,
        ),
      );
    } on DioException catch (error) {
      final status = error.response?.statusCode;
      _zoneMessage = status == 401 || status == 403
          ? 'دسترسی دریافت محدوده نقشه تأیید نشد؛ نمای پیش‌فرض نمایش داده می‌شود.'
          : 'محدوده نقشه دریافت نشد؛ نمای پیش‌فرض نمایش داده می‌شود.';
      debugPrint(
        '[map] zone endpoint failed: status=$status type=${error.type}',
      );
      if (mounted) setState(() {});
    } catch (error) {
      _zoneMessage = 'محدوده نقشه دریافت نشد؛ نمای پیش‌فرض نمایش داده می‌شود.';
      debugPrint('[map] zone fit failed: ${error.runtimeType}');
      if (mounted) setState(() {});
    }
  }

  Future<void> _updateSource() async {
    if (_sourceReady && _map != null) {
      await _map!.setGeoJsonSource('authorized-risers', _geoJson);
    }
  }

  void _onCameraIdle() {
    _cameraDebounce?.cancel();
    _cameraDebounce = Timer(const Duration(milliseconds: 350), () {
      if (_loadingRisers) {
        _reloadAfterRequest = true;
      } else {
        _loadVisibleRisers();
      }
      unawaited(_refreshWmsOverlays());
    });
  }

  Future<void> _loadWmsLayers() async {
    if (_wmsCatalogLoading) return;
    final session = ref.read(sessionProvider);
    final userId = int.tryParse('${session.user?['id'] ?? ''}');
    final api = session.api;
    if (api == null || userId == null || session.token == null) {
      if (mounted) {
        setState(
          () => _wmsCatalogError =
              'برای دریافت لایه‌های نقشه وارد حساب کاربری شوید.',
        );
      }
      return;
    }
    setState(() {
      _wmsCatalogLoading = true;
      _wmsCatalogError = null;
    });
    try {
      final layers = await _wmsRepository.loadLayers(api);
      final saved = await _wmsPreferences.load(userId);
      final activeIds = layers.map((layer) => layer.id).toSet();
      final settings = <int, WmsViewSetting>{};
      for (final layer in layers) {
        settings[layer.id] =
            saved[layer.id] ??
            WmsViewSetting(
              visible: layer.defaultVisible,
              opacity: layer.opacity,
            );
      }
      final removedIds = _wmsLayers
          .map((layer) => layer.id)
          .where((id) => !activeIds.contains(id))
          .toList();
      for (final id in removedIds) {
        await _removeWmsImage(id);
      }
      if (!mounted) return;
      setState(() {
        _wmsLayers = layers;
        _wmsSettings = settings;
        _wmsStatuses.removeWhere((id, _) => !activeIds.contains(id));
        _lastWmsRequestKeys.removeWhere((id, _) => !activeIds.contains(id));
      });
      await _wmsPreferences.save(userId, settings, activeIds);
      await _refreshWmsOverlays(force: true);
    } catch (exception) {
      for (final id in _wmsImageSources.toList()) {
        await _removeWmsImage(id);
      }
      if (!mounted) return;
      setState(() {
        _wmsLayers = [];
        _wmsSettings = {};
        _wmsCatalogError = _wmsErrorMessage(exception);
      });
    } finally {
      if (mounted) setState(() => _wmsCatalogLoading = false);
    }
  }

  Future<void> _refreshWmsOverlays({
    bool force = false,
    int? onlyLayerId,
  }) async {
    final map = _map;
    final session = ref.read(sessionProvider);
    final api = session.api;
    if (!_sourceReady ||
        map == null ||
        api == null ||
        session.serverUrl == null) {
      return;
    }
    if (_wmsRefreshInFlight) {
      _wmsRefreshPending = true;
      return;
    }
    _wmsRefreshInFlight = true;
    try {
      final bounds = await map.getVisibleRegion();
      final size = _wmsImageSize();
      final layers = _wmsLayers
          .where(
            (layer) =>
                (onlyLayerId == null || layer.id == onlyLayerId) &&
                (_wmsSettings[layer.id]?.visible ?? layer.defaultVisible),
          )
          .toList();
      for (final layer in layers) {
        if (!mounted || _map != map) break;
        final setting =
            _wmsSettings[layer.id] ??
            WmsViewSetting(
              visible: layer.defaultVisible,
              opacity: layer.opacity,
            );
        final request = WmsGetMapRequestBuilder.build(
          layer: layer,
          bounds: bounds,
          width: size.$1,
          height: size.$2,
        );
        final requestKey =
            '${layer.proxyUrl}|${layer.version}|${layer.preferredCrs}|${request.bbox}|${request.width}x${request.height}';
        if (!force &&
            _lastWmsRequestKeys[layer.id] == requestKey &&
            _wmsImageSources.contains(layer.id)) {
          continue;
        }
        setState(
          () => _wmsStatuses[layer.id] = const WmsLayerStatus(loading: true),
        );
        try {
          final bytes = await _wmsRepository.loadImage(
            api: api,
            serverUrl: session.serverUrl!,
            layer: layer,
            bbox: request.bbox,
            width: request.width,
            height: request.height,
          );
          final sourceId = 'wms-image-source-${layer.id}';
          final layerId = 'wms-image-layer-${layer.id}';
          if (_wmsImageSources.contains(layer.id)) {
            await map.updateImageSource(sourceId, bytes, request.coordinates);
          } else {
            await map.addImageSource(sourceId, bytes, request.coordinates);
            await map.addImageLayerBelow(
              layerId,
              sourceId,
              'authorized-riser-circles',
            );
            _wmsImageSources.add(layer.id);
          }
          await map.setLayerProperties(
            layerId,
            RasterLayerProperties(rasterOpacity: setting.opacity),
          );
          _lastWmsRequestKeys[layer.id] = requestKey;
          if (mounted) {
            setState(() => _wmsStatuses[layer.id] = const WmsLayerStatus());
          }
        } catch (exception) {
          final statusCode = exception is DioException
              ? exception.response?.statusCode
              : null;
          if (statusCode == 401 || statusCode == 403) {
            await _removeWmsImage(layer.id);
          }
          if (mounted) {
            setState(
              () => _wmsStatuses[layer.id] = WmsLayerStatus(
                message: _wmsErrorMessage(exception),
              ),
            );
          }
        }
      }
    } catch (exception) {
      if (mounted && onlyLayerId != null) {
        setState(
          () => _wmsStatuses[onlyLayerId] = WmsLayerStatus(
            message: _wmsErrorMessage(exception),
          ),
        );
      }
    } finally {
      _wmsRefreshInFlight = false;
      if (_wmsRefreshPending && mounted) {
        _wmsRefreshPending = false;
        unawaited(_refreshWmsOverlays(force: true));
      }
    }
  }

  (int, int) _wmsImageSize() {
    final renderObject = _mapWidgetKey.currentContext?.findRenderObject();
    final devicePixelRatio = MediaQuery.devicePixelRatioOf(context);
    if (renderObject is RenderBox && renderObject.hasSize) {
      return (
        (renderObject.size.width * devicePixelRatio)
            .round()
            .clamp(256, 2048)
            .toInt(),
        (renderObject.size.height * devicePixelRatio)
            .round()
            .clamp(256, 2048)
            .toInt(),
      );
    }
    final screen = MediaQuery.sizeOf(context);
    return (
      (screen.width * devicePixelRatio).round().clamp(256, 2048).toInt(),
      (screen.height * devicePixelRatio * .72).round().clamp(256, 2048).toInt(),
    );
  }

  String _wmsErrorMessage(Object exception) {
    if (exception is DioException) {
      final status = exception.response?.statusCode;
      if (status == 401) return 'نشست شما منقضی شده است؛ دوباره وارد شوید.';
      if (status == 403) return 'مجوز نمایش این لایه برای حساب شما تأیید نشد.';
      if (exception.type == DioExceptionType.connectionError ||
          exception.type == DioExceptionType.connectionTimeout ||
          exception.type == DioExceptionType.receiveTimeout) {
        return 'اتصال اینترنت برقرار نیست؛ پس از اتصال دوباره تلاش کنید.';
      }
    }
    return 'بارگذاری لایه نقشه ناموفق بود؛ اتصال و تنظیمات سرویس را بررسی کنید.';
  }

  Future<void> _removeWmsImage(int layerId) async {
    final map = _map;
    if (map != null && _wmsImageSources.contains(layerId)) {
      try {
        await map.removeLayer('wms-image-layer-$layerId');
      } catch (_) {}
      try {
        await map.removeSource('wms-image-source-$layerId');
      } catch (_) {}
    }
    _wmsImageSources.remove(layerId);
    _lastWmsRequestKeys.remove(layerId);
  }

  Future<void> _toggleWmsLayer(WmsMapLayer layer, bool visible) async {
    final current =
        _wmsSettings[layer.id] ??
        WmsViewSetting(visible: layer.defaultVisible, opacity: layer.opacity);
    setState(() {
      _wmsSettings[layer.id] = current.copyWith(visible: visible);
      _wmsStatuses[layer.id] = const WmsLayerStatus();
    });
    await _persistWmsSettings();
    if (visible) {
      await _refreshWmsOverlays(force: true, onlyLayerId: layer.id);
    } else {
      await _removeWmsImage(layer.id);
    }
  }

  Future<void> _setWmsOpacity(WmsMapLayer layer, double opacity) async {
    final current =
        _wmsSettings[layer.id] ??
        WmsViewSetting(visible: layer.defaultVisible, opacity: layer.opacity);
    setState(
      () => _wmsSettings[layer.id] = current.copyWith(
        opacity: opacity.clamp(0, 1).toDouble(),
      ),
    );
    if (_wmsImageSources.contains(layer.id)) {
      try {
        await _map?.setLayerProperties(
          'wms-image-layer-${layer.id}',
          RasterLayerProperties(rasterOpacity: opacity),
        );
      } catch (_) {}
    }
    await _persistWmsSettings();
  }

  Future<void> _persistWmsSettings() async {
    final userId = int.tryParse(
      '${ref.read(sessionProvider).user?['id'] ?? ''}',
    );
    if (userId == null) return;
    await _wmsPreferences.save(
      userId,
      _wmsSettings,
      _wmsLayers.map((layer) => layer.id).toSet(),
    );
  }

  Future<void> _openWmsLayers() async {
    if (_wmsLayers.isEmpty && !_wmsCatalogLoading && _wmsCatalogError == null) {
      await _loadWmsLayers();
    }
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => WmsLayersSheet(
          layers: _wmsLayers,
          settings: _wmsSettings,
          statuses: _wmsStatuses,
          loadingCatalog: _wmsCatalogLoading,
          catalogError: _wmsCatalogError,
          onRefresh: () async {
            await _loadWmsLayers();
            setSheetState(() {});
          },
          onToggle: (layer, visible) async {
            await _toggleWmsLayer(layer, visible);
            setSheetState(() {});
          },
          onOpacity: (layer, opacity) async {
            await _setWmsOpacity(layer, opacity);
            setSheetState(() {});
          },
          onRetry: (layer) async {
            await _refreshWmsOverlays(force: true, onlyLayerId: layer.id);
            setSheetState(() {});
          },
        ),
      ),
    );
  }

  Future<void> _loadVisibleRisers({bool reset = true}) async {
    final map = _map;
    final session = ref.read(sessionProvider);
    if (map == null || _profile == null || _loadingRisers) return;
    if (reset || _visibleQuery == null) {
      final bounds = await map.getVisibleRegion();
      final west = bounds.southwest.longitude;
      final south = bounds.southwest.latitude;
      final east = bounds.northeast.longitude;
      final north = bounds.northeast.latitude;
      if (east - west > 2 || north - south > 2) {
        if (mounted) {
          setState(
            () => _error =
                'برای بارگذاری علمک‌ها، نقشه را بیشتر بزرگ‌نمایی کنید.',
          );
        }
        return;
      }
      _visibleQuery = {
        'bbox_west': west,
        'bbox_south': south,
        'bbox_east': east,
        'bbox_north': north,
        if (_search.isNotEmpty) 'search': _search,
        if (_status != null) 'status': _status,
        'per_page': 200,
      };
      _nextPage = 1;
      _hasMore = false;
      if (reset) _risers = [];
    }
    setState(() {
      _loadingRisers = true;
      _error = null;
    });
    try {
      final response = await session.api!.dio.get(
        'v1/supervisor/risers',
        queryParameters: {..._visibleQuery!, 'page': _nextPage},
      );
      final page = Map<String, dynamic>.from(response.data as Map);
      final rows = List<Map<String, dynamic>>.from(
        (page['data'] as List).map(
          (item) => Map<String, dynamic>.from(item as Map),
        ),
      );
      if (!mounted) return;
      setState(() {
        _risers = [..._risers, ...rows].take(2000).toList();
        _nextPage++;
        _hasMore = page['next_page_url'] != null && _risers.length < 2000;
      });
      await _refreshPendingRisers();
      await _updateSource();
    } catch (exception) {
      if (mounted) {
        setState(
          () => _error = exception is DioException
              ? 'دریافت علمک‌های این محدوده ناموفق بود. اتصال و مجوز خود را بررسی کنید.'
              : exception.toString(),
        );
      }
    } finally {
      if (mounted) setState(() => _loadingRisers = false);
      if (_reloadAfterRequest && mounted) {
        _reloadAfterRequest = false;
        await _loadVisibleRisers();
      }
    }
  }

  Future<void> _refreshPendingRisers() async {
    final pending = await CaseRepository(ref.read(sessionProvider)).pending();
    final ids = <int>{};
    for (final row in pending) {
      final raw = row['payload'];
      if (raw is String) {
        final payload = jsonDecode(raw);
        if (payload is Map) {
          final id = int.tryParse('${payload['riser_id'] ?? ''}');
          if (id != null) ids.add(id);
        }
      } else if (raw is Map) {
        final id = int.tryParse('${raw['riser_id'] ?? ''}');
        if (id != null) ids.add(id);
      }
    }
    if (mounted) {
      setState(() {
        _pendingRiserIds
          ..clear()
          ..addAll(ids);
      });
    }
  }

  Future<void> _onMapClick(Point<double> point, LatLng coordinates) async {
    debugPrint(
      '[map] onMapClick received ready=$_sourceReady busy=$_selectingRiser query=$_featureQueryInFlight screenPx=(${point.x},${point.y}) geo=(${coordinates.longitude},${coordinates.latitude})',
    );
    if (!_sourceReady || _selectingRiser || _featureQueryInFlight) return;
    final map = _map;
    if (map == null) return;
    _featureQueryInFlight = true;
    try {
      final hitArea = Rect.fromLTRB(
        point.x - 18,
        point.y - 18,
        point.x + 18,
        point.y + 18,
      );
      final features = await map.queryRenderedFeaturesInRect(hitArea, [
        'authorized-riser-circles',
        'riser-labels',
      ], null);
      debugPrint(
        '[map] queryRenderedFeaturesInRect returned ${features.length} feature(s), rect=$hitArea',
      );
      final ids = <int>{};
      for (final feature in features) {
        final id = resolveAuthorizedRiserId(
          Map<String, dynamic>.from(feature as Map),
          _risers,
        );
        debugPrint(
          '[map] candidate featureId=${feature['id']} props=${feature['properties']} authorizedId=$id',
        );
        if (id != null) {
          ids.add(id);
        } else {
          debugPrint(
            '[map] feature rejected: invalid ID or ID not in authorized riser list',
          );
        }
      }
      debugPrint('[map] authorized hit count=${ids.length}');
      if (ids.isEmpty) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('در این نقطه علمک قابل انتخابی وجود ندارد.'),
            ),
          );
        }
        return;
      }
      if (ids.length > 1) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'چند علمک نزدیک هم هستند؛ نقشه را بزرگ‌نمایی کنید.',
              ),
            ),
          );
        }
        return;
      }
      await _showRiser(ids.single);
    } catch (error) {
      debugPrint('[map] hit test exception ${error.runtimeType}: $error');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('انتخاب علمک از نقشه انجام نشد؛ دوباره تلاش کنید.'),
          ),
        );
      }
    } finally {
      _featureQueryInFlight = false;
    }
  }

  Future<void> _showRiser(int id) async {
    if (_selectingRiser || !mounted) return;
    _selectingRiser = true;
    try {
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (sheetContext) => RiserDetailsSheet(
          key: ValueKey('riser-$id'),
          riserId: id,
          loadDetails: (riserId) async {
            final response = await ref
                .read(sessionProvider)
                .api!
                .dio
                .get('v1/supervisor/risers/$riserId/details');
            return Map<String, dynamic>.from(response.data as Map);
          },
          onCreateCase: (riserId) {
            Navigator.pop(sheetContext);
            _createCaseFromMap(riserId);
          },
          onOpenCase: (caseId) {
            Navigator.pop(sheetContext);
            _openCaseFromMap(caseId);
          },
          onShowMap: () => Navigator.pop(sheetContext),
          syncPending: _pendingRiserIds.contains(id),
        ),
      );
    } finally {
      _selectingRiser = false;
    }
  }

  Future<void> _createCaseFromMap(int riserId) async {
    final result = await GoRouter.of(context).push('/case/new/$riserId');
    if (!mounted) return;
    await _refreshPendingRisers();
    await _loadVisibleRisers();
    if (!mounted) return;
    if (result is Map && result['pending_riser_id'] == riserId) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            '\u067e\u0631\u0648\u0646\u062f\u0647 \u0645\u062d\u0644\u06cc \u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0627\u0633\u062a.',
          ),
        ),
      );
    } else if (result == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            '\u0648\u0636\u0639\u06cc\u062a \u0639\u0644\u0645\u06a9 \u0627\u0632 \u0633\u0631\u0648\u0631 \u0628\u0627\u0632\u0628\u0627\u0631\u06af\u0630\u0627\u0631\u06cc \u0634\u062f.',
          ),
        ),
      );
    }
  }

  Future<void> _openCaseFromMap(int caseId) async {
    await GoRouter.of(context).push('/case/$caseId');
    if (!mounted) return;
    await _refreshPendingRisers();
    await _loadVisibleRisers();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _profile == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return Scaffold(
      appBar: AppBar(
        title: const Text('نقشه علمک‌ها'),
        actions: [
          IconButton(
            tooltip: 'لایه‌های نقشه',
            onPressed: _openWmsLayers,
            icon: const Icon(Icons.layers_outlined),
          ),
          IconButton(
            tooltip: 'تازه‌سازی نقشه',
            onPressed: _loadMap,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    textInputAction: TextInputAction.search,
                    onSubmitted: (value) {
                      _search = value.trim();
                      _loadVisibleRisers();
                    },
                    decoration: InputDecoration(
                      hintText: 'جست‌وجوی GISCode',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: IconButton(
                        tooltip: 'پاک کردن جست‌وجو',
                        onPressed: () {
                          _search = '';
                          _loadVisibleRisers();
                        },
                        icon: const Icon(Icons.clear),
                      ),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                PopupMenuButton<String?>(
                  tooltip: 'فیلتر وضعیت',
                  onSelected: (value) {
                    _status = value;
                    _loadVisibleRisers();
                  },
                  itemBuilder: (_) => const [
                    PopupMenuItem(value: null, child: Text('همه وضعیت‌ها')),
                    PopupMenuItem(value: 'open', child: Text('باز')),
                    PopupMenuItem(value: 'submitted', child: Text('ارسال‌شده')),
                    PopupMenuItem(value: 'returned', child: Text('برگشتی')),
                    PopupMenuItem(value: 'completed', child: Text('تکمیل‌شده')),
                  ],
                  child: const CircleAvatar(child: Icon(Icons.filter_list)),
                ),
              ],
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          Expanded(
            child: _profile == null
                ? const Center(child: Text('تنظیمات نقشه در دسترس نیست.'))
                : Stack(
                    children: [
                      MapLibreMap(
                        key: _mapWidgetKey,
                        styleString: _rasterStyle(
                          _profile!['tile_url'] as String,
                        ),
                        initialCameraPosition: const CameraPosition(
                          target: LatLng(35.7, 51.4),
                          zoom: 12,
                        ),
                        minMaxZoomPreference: MinMaxZoomPreference(
                          (_profile!['min_zoom'] as num?)?.toDouble() ?? 0,
                          (_profile!['max_zoom'] as num?)?.toDouble() ?? 19,
                        ),
                        onMapCreated: _onMapCreated,
                        onStyleLoadedCallback: _onStyleLoaded,
                        onMapClick: _onMapClick,
                        onCameraIdle: _onCameraIdle,
                        featureTapsTriggersMapClick: true,
                        compassEnabled: true,
                        attributionButtonMargins: const Point(12, 72),
                      ),
                      if (_zoneMessage != null)
                        PositionedDirectional(
                          start: 10,
                          top: 10,
                          child: Material(
                            color: Colors.white.withValues(alpha: .94),
                            borderRadius: BorderRadius.circular(12),
                            child: Padding(
                              padding: const EdgeInsets.all(9),
                              child: Text(
                                _zoneMessage!,
                                textDirection: TextDirection.rtl,
                                style: const TextStyle(fontSize: 11),
                              ),
                            ),
                          ),
                        ),
                      if (_zoneMessage == null)
                        PositionedDirectional(
                          start: 10,
                          top: 10,
                          child: Chip(
                            avatar: const Icon(
                              Icons.location_on_outlined,
                              size: 18,
                            ),
                            label: Text(
                              _isSuperadmin
                                  ? 'نمای سراسری نقشه'
                                  : 'علمک در محدوده نقشه',
                            ),
                          ),
                        ),
                      PositionedDirectional(
                        end: 10,
                        top: 52,
                        child: _MapLegend(pending: _pendingRiserIds.isNotEmpty),
                      ),
                      PositionedDirectional(
                        start: 10,
                        bottom: 10,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: .92),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            _profile!['attribution']?.toString() ??
                                'تنظیمات نقشه در دسترس نیست.',
                            style: const TextStyle(fontSize: 11),
                          ),
                        ),
                      ),
                      if (_loadingRisers)
                        const PositionedDirectional(
                          top: 8,
                          end: 8,
                          child: CircularProgressIndicator(),
                        ),
                      if (_hasMore)
                        PositionedDirectional(
                          bottom: 58,
                          end: 10,
                          child: FilledButton.tonal(
                            onPressed: _loadingRisers
                                ? null
                                : () => _loadVisibleRisers(reset: false),
                            child: const Text('بارگذاری علمک‌های بیشتر'),
                          ),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _MapLegend extends StatelessWidget {
  const _MapLegend({required this.pending});
  final bool pending;
  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white.withValues(alpha: .94),
    borderRadius: BorderRadius.circular(10),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      child: Wrap(
        spacing: 8,
        runSpacing: 4,
        children: [
          _LegendItem(
            color: const Color(0xff607d8b),
            label: '\u0639\u0627\u062f\u06cc',
          ),
          _LegendItem(
            color: const Color(0xffd97706),
            label:
                '\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u062a\u0639\u0645\u06cc\u0631',
          ),
          _LegendItem(
            color: const Color(0xff2563eb),
            label:
                '\u062f\u0631 \u062d\u0627\u0644 \u062a\u0639\u0645\u06cc\u0631',
          ),
          _LegendItem(
            color: const Color(0xff7c3aed),
            label:
                '\u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0623\u06cc\u06cc\u062f',
          ),
          _LegendItem(
            color: const Color(0xff059669),
            label: '\u062a\u0623\u06cc\u06cc\u062f\u0634\u062f\u0647',
          ),
          _LegendItem(
            color: const Color(0xfff97316),
            label: '\u0628\u0631\u06af\u0634\u062a\u06cc',
          ),
          if (pending)
            const _LegendItem(
              color: Color(0xfff59e0b),
              label:
                  '\u062f\u0631 \u0635\u0641 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc',
            ),
        ],
      ),
    ),
  );
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({required this.color, required this.label});
  final Color color;
  final String label;
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 9,
        height: 9,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
      const SizedBox(width: 4),
      Text(label, style: const TextStyle(fontSize: 10)),
    ],
  );
}
