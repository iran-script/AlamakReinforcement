import 'package:dio/dio.dart';

class ApiClient {
  ApiClient(String serverUrl, {String? token})
      : dio = Dio(BaseOptions(
          baseUrl: '${serverUrl.replaceFirst(RegExp(r'/+$'), '')}/api/',
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 20),
          sendTimeout: const Duration(seconds: 30),
          headers: <String, Object>{
            'Accept': 'application/json',
            if (token != null) 'Authorization': 'Bearer $token',
          },
        ));

  final Dio dio;

  static String? messageFrom(Object? error) {
    if (error == null) return null;
    if (error is DioException) {
      final data = error.response?.data;
      if (data is Map && data['message'] is String) return data['message'] as String;
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout) {
        return 'ارتباط با سرور برقرار نشد.';
      }
      if (error.type == DioExceptionType.connectionError) {
        return 'اتصال به سرور داخلی برقرار نشد.';
      }
    }
    return 'ارتباط با سرور برقرار نشد.';
  }
}
