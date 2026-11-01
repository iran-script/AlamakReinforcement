import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/data/api_client.dart';
import '../../core/data/draft_store.dart';

final sessionProvider = ChangeNotifierProvider<SessionController>((ref) => SessionController());

class SessionController extends ChangeNotifier {
  static const _urlKey = 'server_url';
  static const _tokenKey = 'supervisor_token';
  static const _userKey = 'supervisor_user';
  static const _mapKey = 'last_valid_map_profile';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  final DraftStore drafts = DraftStore();

  String? serverUrl;
  String? token;
  Map<String, dynamic>? user;
  bool initialized = false;
  bool busy = false;
  bool offline = false;
  String? error;

  ApiClient? get api => serverUrl == null ? null : ApiClient(serverUrl!, token: token);

  Future<void> initialize() async {
    if (kReleaseMode) await drafts.clearAll();
    serverUrl = await _storage.read(key: _urlKey);
    token = await _storage.read(key: _tokenKey);
    final cached = await _storage.read(key: _userKey);
    if (cached != null) user = Map<String, dynamic>.from(jsonDecode(cached) as Map);
    initialized = true;
    notifyListeners();
    if (token != null && serverUrl != null) await refreshUser();
  }

  String? validateServerUrl(String input) {
    final uri = Uri.tryParse(input.trim());
    if (uri == null || !uri.hasAuthority || uri.host.isEmpty || uri.userInfo.isNotEmpty || uri.hasQuery || uri.hasFragment) {
      return 'آدرس سرور معتبر نیست.';
    }
    if (uri.pathSegments.any((part) => part == '..') || uri.path.contains('\\')) {
      return 'مسیر آدرس معتبر نیست.';
    }
    if (kReleaseMode && uri.scheme != 'https') {
      return 'در نسخه عملیاتی فقط HTTPS پذیرفته می‌شود.';
    }
    if (!kReleaseMode && uri.scheme == 'http' && !_isDevelopmentHost(uri.host)) {
      return 'در حالت توسعه، HTTP فقط برای میزبان محلی مجاز است.';
    }
    if (!const ['https', 'http'].contains(uri.scheme)) {
      return 'فقط نشانی HTTP یا HTTPS پذیرفته می‌شود.';
    }
    return null;
  }

  bool _isDevelopmentHost(String host) {
    final normalized = host.toLowerCase();
    if (normalized == 'localhost' || normalized.endsWith('.local') || normalized == '10.0.2.2') return true;
    final address = InternetAddress.tryParse(normalized);
    if (address == null) return false;
    if (address.type == InternetAddressType.IPv6) return address.isLoopback || address.isLinkLocal;
    final octets = normalized.split('.').map(int.tryParse).toList();
    if (octets.length != 4 || octets.any((value) => value == null)) return false;
    final a = octets[0]!, b = octets[1]!;
    return a == 10 || a == 127 || (a == 192 && b == 168) || (a == 172 && b >= 16 && b <= 31);
  }

  Future<bool> configureServer(String value) async {
    final normalized = value.trim().replaceFirst(RegExp(r'/+$'), '');
    final problem = validateServerUrl(normalized);
    if (problem != null) { error = problem; notifyListeners(); return false; }
    busy = true; error = null; notifyListeners();
    try {
      final result = await ApiClient(normalized).dio.get('v1/mobile/health');
      if (result.data is! Map || result.data['service'] != 'alamak-supervisor-api') {
        throw const FormatException('آدرس واردشده معتبر نیست.');
      }
      if (serverUrl != null && serverUrl != normalized) {
        final previousUserId = int.tryParse('${user?['id'] ?? ''}');
        if (previousUserId != null) await drafts.clearUser(previousUserId);
        await _clearCredentials();
      }
      serverUrl = normalized;
      await _storage.write(key: _urlKey, value: normalized);
      offline = false;
      return true;
    } catch (exception) {
      error = exception is FormatException ? exception.message : ApiClient.messageFrom(exception);
      return false;
    } finally { busy = false; notifyListeners(); }
  }

  Future<bool> login(String username, String password) async {
    final client = api;
    if (client == null) { error = 'اتصال به سرور برقرار نیست؛ تنظیمات سرور را بررسی کنید.'; notifyListeners(); return false; }
    busy = true; error = null; notifyListeners();
    try {
      final response = await client.dio.post('login', data: {'username': username.trim(), 'password': password});
      final data = Map<String, dynamic>.from(response.data as Map);
      final userData = Map<String, dynamic>.from(data['user'] as Map);
      if (userData['role'] != 'supervisor' || data['token'] is! String) {
        throw const FormatException('آدرس واردشده معتبر نیست.');
      }
      token = data['token'] as String;
      user = userData;
      await _storage.write(key: _tokenKey, value: token);
      await _storage.write(key: _userKey, value: jsonEncode(user));
      await refreshUser();
      return token != null;
    } catch (exception) {
      error = exception is FormatException ? exception.message : ApiClient.messageFrom(exception);
      return false;
    } finally { busy = false; notifyListeners(); }
  }

  Future<void> refreshUser() async {
    if (api == null || token == null) return;
    try {
      final response = await api!.dio.get('v1/supervisor/me');
      final fresh = Map<String, dynamic>.from(response.data['user'] as Map);
      if (fresh['role'] != 'supervisor') { await logout(); return; }
      user = fresh;
      offline = false;
      await _storage.write(key: _userKey, value: jsonEncode(user));
    } catch (exception) {
      final status = exception is DioException ? exception.response?.statusCode : null;
      if (status == 401 || status == 403) {
        await _clearCredentials();
      } else {
        offline = true;
        error = ApiClient.messageFrom(exception);
      }
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>?> loadMapProfile() async {
    final cached = await _storage.read(key: _mapKey);
    try {
      final response = await api!.dio.get('v1/supervisor/map/config');
      final profile = Map<String, dynamic>.from(response.data['map_profile'] as Map);
      if (profile['service_type'] != 'xyz_raster' || profile['tile_url'] is! String) {
        throw const FormatException('تنظیمات نقشه پایه نامعتبر است.');
      }
      if (kReleaseMode && !_isInternalMapTile(profile['tile_url'] as String)) {
        throw const FormatException('آدرس واردشده معتبر نیست.');
      }
      await _storage.write(key: _mapKey, value: jsonEncode(profile));
      return profile;
    } catch (_) {
      if (cached != null) {
        final profile = Map<String, dynamic>.from(jsonDecode(cached) as Map);
        if (kReleaseMode && !_isInternalMapTile(profile['tile_url'] as String)) return null;
        return profile;
      }
      if (kDebugMode) return {'service_type': 'xyz_raster', 'tile_url': 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'min_zoom': 0, 'max_zoom': 19, 'attribution': '© OpenStreetMap contributors'};
      return null;
    }
  }

  bool _isInternalMapTile(String tileUrl) {
    final uri = Uri.tryParse(tileUrl);
    if (uri == null) return false;
    if (!uri.hasAuthority) return tileUrl.startsWith('/') && !tileUrl.startsWith('//');
    final server = Uri.parse(serverUrl!);
    return uri.scheme == 'https' && uri.host.toLowerCase() == server.host.toLowerCase() &&
        (uri.port == 443 || uri.hasPort == false);
  }

  Future<void> logout() async {
    final oldUserId = int.tryParse('${user?['id'] ?? ''}');
    try { await api?.dio.post('logout'); } catch (_) { /* Always erase local credentials. */ }
    if (oldUserId != null) await drafts.clearUser(oldUserId);
    await _clearCredentials();
    notifyListeners();
  }

  Future<void> _clearCredentials() async {
    token = null; user = null; offline = false;
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
    await _storage.delete(key: _mapKey);
  }
}
