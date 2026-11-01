import 'dart:convert';
import 'dart:math';
import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maplibre_gl/maplibre_gl.dart';

import '../auth/session_controller.dart';

class MapPage extends ConsumerStatefulWidget {
  const MapPage({super.key});
  @override
  ConsumerState<MapPage> createState() => _MapPageState();
}

class _MapPageState extends ConsumerState<MapPage> {
  MapLibreMapController? _map;
  Map<String, dynamic>? _profile;
  List<Map<String, dynamic>> _risers = [];
  bool _loading = true;
  String? _error;
  String _search = '';
  String? _status;
  bool _sourceReady = false;
  bool _loadingRisers = false;
  bool _reloadAfterRequest = false;
  bool _hasMore = false;
  int _nextPage = 1;
  Map<String, dynamic>? _visibleQuery;
  Timer? _cameraDebounce;

  @override
  void initState() { super.initState(); _loadMap(); }

  @override
  void dispose() { _cameraDebounce?.cancel(); super.dispose(); }

  Future<void> _loadMap() async {
    setState(() { _loading = true; _error = null; });
    final session = ref.read(sessionProvider);
    try {
      final profile = await session.loadMapProfile();
      if (profile == null) throw StateError('تنظیمات نقشه در دسترس نیست.');
      if (!mounted) return;
      setState(() { _profile = profile; _loading = false; });
    } catch (exception) {
      if (!mounted) return;
      setState(() { _loading = false; _error = exception is DioException ? 'بارگذاری نقشه ناموفق بود. اتصال سرور را بررسی کنید.' : exception.toString(); });
    }
  }

  String _rasterStyle(String tileUrl) {
    final server = ref.read(sessionProvider).serverUrl!;
    final resolved = tileUrl.startsWith('/') ? Uri.parse(server).resolve(tileUrl).toString() : tileUrl;
    final minZoom = (_profile?['min_zoom'] as num?)?.toInt() ?? 0;
    final maxZoom = (_profile?['max_zoom'] as num?)?.toInt() ?? 19;
    return jsonEncode({
      'version': 8,
      'name': _profile?['name'] ?? 'Alamak Internal Raster',
      'sources': {'alamak-base': {'type': 'raster', 'tiles': [resolved], 'tileSize': 256, 'minzoom': minZoom, 'maxzoom': maxZoom, 'attribution': _profile?['attribution'] ?? ''}},
      'layers': [
        {'id': 'background', 'type': 'background', 'paint': {'background-color': '#e8edf0'}},
        {'id': 'alamak-base-layer', 'type': 'raster', 'source': 'alamak-base', 'minzoom': minZoom, 'maxzoom': maxZoom},
      ],
    });
  }

  Map<String, dynamic> get _geoJson => {
    'type': 'FeatureCollection',
    'features': _risers.map((riser) => {
      'type': 'Feature',
      'geometry': {'type': 'Point', 'coordinates': [riser['longitude'], riser['latitude']]},
      'properties': {'id': riser['id'], 'giscode': riser['r_giscode'] ?? riser['giscode'] ?? '', 'status': riser['maintenance_status'] ?? riser['status'] ?? 'default'},
    }).toList(),
  };

  Future<void> _onMapCreated(MapLibreMapController controller) async {
    _map = controller;
    _sourceReady = false;
  }

  Future<void> _onStyleLoaded() async {
    final map = _map;
    if (map == null) return;
    try {
      await map.addGeoJsonSource('authorized-risers', _geoJson, promoteId: 'id');
      await map.addCircleLayer('authorized-risers', 'authorized-riser-circles', CircleLayerProperties(
        circleRadius: ['interpolate', ['linear'], ['zoom'], 5, 4, 15, 8],
        circleColor: ['match', ['get', 'status'],
          'open', '#d97706', 'in_progress', '#2563eb', 'submitted', '#7c3aed',
          'returned', '#dc2626', 'completed', '#059669',
          'flagRiser', '#d97706', 'submitOperation', '#059669', 'resolveLead', '#2563eb', 'error', '#dc2626', '#607d8b'],
        circleStrokeColor: '#ffffff', circleStrokeWidth: 2,
      ));
      await map.addSymbolLayer('authorized-risers', 'riser-labels', SymbolLayerProperties(
        textField: '{giscode}', textSize: 10, textColor: '#17212b',
        textHaloColor: '#ffffff', textHaloWidth: 1.2, textOffset: const [0, 1.1], textAnchor: 'top',
      ), minzoom: 14);
      _sourceReady = true;
      await _loadVisibleRisers();
    } catch (exception) {
      if (mounted) setState(() => _error = 'بارگذاری علمک‌ها ناموفق بود.');
    }
  }

  Future<void> _updateSource() async {
    if (_sourceReady && _map != null) await _map!.setGeoJsonSource('authorized-risers', _geoJson);
  }

  void _onCameraIdle() {
    _cameraDebounce?.cancel();
    _cameraDebounce = Timer(const Duration(milliseconds: 350), () {
      if (_loadingRisers) {
        _reloadAfterRequest = true;
      } else {
        _loadVisibleRisers();
      }
    });
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
        if (mounted) setState(() => _error = 'برای بارگذاری علمک‌ها، نقشه را بیشتر بزرگ‌نمایی کنید.');
        return;
      }
      _visibleQuery = {
        'bbox_west': west, 'bbox_south': south, 'bbox_east': east, 'bbox_north': north,
        if (_search.isNotEmpty) 'search': _search,
        if (_status != null) 'status': _status,
        'per_page': 200,
      };
      _nextPage = 1;
      _hasMore = false;
      if (reset) _risers = [];
    }
    setState(() { _loadingRisers = true; _error = null; });
    try {
      final response = await session.api!.dio.get('v1/supervisor/risers', queryParameters: {..._visibleQuery!, 'page': _nextPage});
      final page = Map<String, dynamic>.from(response.data as Map);
      final rows = List<Map<String, dynamic>>.from((page['data'] as List).map((item) => Map<String, dynamic>.from(item as Map)));
      if (!mounted) return;
      setState(() {
        _risers = [..._risers, ...rows].take(2000).toList();
        _nextPage++;
        _hasMore = page['next_page_url'] != null && _risers.length < 2000;
      });
      await _updateSource();
    } catch (exception) {
      if (mounted) { setState(() => _error = exception is DioException
          ? 'دریافت علمک‌های این محدوده ناموفق بود. اتصال و مجوز خود را بررسی کنید.'
          : exception.toString()); }
    } finally {
      if (mounted) setState(() => _loadingRisers = false);
      if (_reloadAfterRequest && mounted) {
        _reloadAfterRequest = false;
        await _loadVisibleRisers();
      }
    }
  }

  Future<void> _onMapClick(Point<double> point, LatLng coordinates) async {
    final features = await _map?.queryRenderedFeatures(point, ['authorized-riser-circles'], null);
    if (features == null || features.isEmpty) return;
    final properties = Map<String, dynamic>.from(features.first['properties'] as Map? ?? {});
    final id = int.tryParse('${properties['id'] ?? ''}');
    if (id != null) await _showRiser(id);
  }

  Future<void> _showRiser(int id) async {
    try {
      final response = await ref.read(sessionProvider).api!.dio.get('v1/supervisor/risers/$id/details');
      final body = Map<String, dynamic>.from(response.data as Map);
      if (!mounted) return;
      final riser = Map<String, dynamic>.from(body['riser'] as Map);
      showModalBottomSheet<void>(context: context, isScrollControlled: true, showDragHandle: true, builder: (context) => SafeArea(child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 22),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(riser['r_giscode']?.toString() ?? riser['giscode']?.toString() ?? 'علمک', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text('وضعیت GIS'),
          Text('پرونده‌ها: اطلاعات عملیات'),
          const SizedBox(height: 12),
          FilledButton.icon(onPressed: () { Navigator.pop(context); context.push('/case/new/$id'); }, icon: const Icon(Icons.add_location_alt_outlined), label: const Text('ثبت نیاز به تعمیر')),
          const SizedBox(height: 8),
          OutlinedButton.icon(onPressed: () { Navigator.pop(context); context.push('/case/${(body['cases'] as List).isNotEmpty ? body['cases'][0]['id'] : 0}'); }, icon: const Icon(Icons.history), label: const Text('پرونده‌ها: اطلاعات عملیات'),),
        ]),
      )));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('علمک')));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _profile == null) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    return Scaffold(
      appBar: AppBar(title: const Text('نقشه علمک‌ها'), actions: [IconButton(tooltip: 'تازه‌سازی', onPressed: _loadMap, icon: const Icon(Icons.refresh))]),
      body: Column(children: [
        Padding(padding: const EdgeInsets.fromLTRB(12, 4, 12, 8), child: Row(children: [
          Expanded(child: TextField(textInputAction: TextInputAction.search, onSubmitted: (value) { _search = value.trim(); _loadVisibleRisers(); }, decoration: InputDecoration(hintText: 'جست‌وجوی GISCode', prefixIcon: const Icon(Icons.search), suffixIcon: IconButton(tooltip: 'پاک کردن جست‌وجو', onPressed: () { _search = ''; _loadVisibleRisers(); }, icon: const Icon(Icons.clear)), isDense: true))),
          const SizedBox(width: 8),
          PopupMenuButton<String?>(tooltip: 'فیلتر وضعیت', onSelected: (value) { _status = value; _loadVisibleRisers(); }, itemBuilder: (_) => const [PopupMenuItem(value: null, child: Text('همه وضعیت‌ها')), PopupMenuItem(value: 'open', child: Text('باز')), PopupMenuItem(value: 'submitted', child: Text('ارسال‌شده')), PopupMenuItem(value: 'returned', child: Text('برگشتی')), PopupMenuItem(value: 'completed', child: Text('تکمیل‌شده'))], child: const CircleAvatar(child: Icon(Icons.filter_list))),
        ])),
        if (_error != null) Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
        Expanded(child: _profile == null ? const Center(child: Text('تنظیمات نقشه در دسترس نیست.')) : Stack(children: [
          MapLibreMap(
            styleString: _rasterStyle(_profile!['tile_url'] as String),
            initialCameraPosition: const CameraPosition(target: LatLng(35.7, 51.4), zoom: 12),
            minMaxZoomPreference: MinMaxZoomPreference((_profile!['min_zoom'] as num?)?.toDouble() ?? 0, (_profile!['max_zoom'] as num?)?.toDouble() ?? 19),
            onMapCreated: _onMapCreated, onStyleLoadedCallback: _onStyleLoaded,
            onMapClick: _onMapClick, onCameraIdle: _onCameraIdle,
            compassEnabled: true, attributionButtonMargins: const Point(12, 72),
          ),
          PositionedDirectional(start: 10, top: 10, child: Chip(avatar: const Icon(Icons.location_on_outlined, size: 18), label: Text('علمک در محدوده نقشه'))),
          PositionedDirectional(start: 10, bottom: 10, child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: Colors.white.withValues(alpha: .92), borderRadius: BorderRadius.circular(10)), child: Text(_profile!['attribution']?.toString() ?? 'تنظیمات نقشه در دسترس نیست.', style: const TextStyle(fontSize: 11)))),
          if (_loadingRisers) const PositionedDirectional(top: 8, end: 8, child: CircularProgressIndicator()),
          if (_hasMore) PositionedDirectional(bottom: 58, end: 10, child: FilledButton.tonal(onPressed: _loadingRisers ? null : () => _loadVisibleRisers(reset: false), child: const Text('بارگذاری علمک‌های بیشتر'))),
        ])),
      ]),
    );
  }
}
