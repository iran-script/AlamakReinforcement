import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/auth/session_controller.dart';

void main() {
  test('development server URL validation rejects public cleartext hosts', () {
    final controller = SessionController();
    expect(controller.validateServerUrl('http://192.168.1.20:8000'), isNull);
    expect(controller.validateServerUrl('http://10.0.2.2:8000'), isNull);
    expect(controller.validateServerUrl('http://example.com'), isNotNull);
    expect(controller.validateServerUrl('https://user:pass@example.com'), isNotNull);
  });
}
