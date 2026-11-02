import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/core/data/api_client.dart';
import 'package:alamak_supervisor/features/auth/session_controller.dart';
import 'package:alamak_supervisor/features/cases/case_repository.dart';

void main() {
  test('operation form preserves case, idempotency, quantities, and image field', () async {
    final dir = await Directory.systemTemp.createTemp('alamak-upload-test-');
    final image = File('${dir.path}/after.jpg');
    await image.writeAsBytes([0xff, 0xd8, 0xff, 0xd9]);
    try {
      final form = CaseRepository(SessionController()).formData({
        'client_uuid': 'c8d588f3-50c5-4128-8340-d9b5aa4ed9d3',
        'operation_date': '2026-11-02',
        'operation_category_id': 3,
        'materials[12]': '2.5',
      }, [image.path], 'photos_after');
      expect(form.fields.any((e) => e.key == 'client_uuid' && e.value == 'c8d588f3-50c5-4128-8340-d9b5aa4ed9d3'), isTrue);
      expect(form.fields.any((e) => e.key == 'materials[12]' && e.value == '2.5'), isTrue);
      expect(form.files.single.key, 'photos_after[0]');
      expect(form.files.single.value.filename, 'after.jpg');
    } finally {
      await dir.delete(recursive: true);
    }
  });

  test('API errors preserve server validation messages', () {
    final error = ApiClient.messageFrom(DioException(
      requestOptions: RequestOptions(path: '/cases'),
      response: Response(requestOptions: RequestOptions(path: '/cases'), statusCode: 422, data: {'message': 'Invalid material quantity.'}),
      type: DioExceptionType.badResponse,
    ));
    expect(error, 'Invalid material quantity.');
  });
}
