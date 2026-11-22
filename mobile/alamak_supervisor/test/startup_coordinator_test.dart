import 'package:alamak_supervisor/features/auth/startup_coordinator.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('keeps the startup gate open for at least three seconds', () async {
    var clock = DateTime.utc(2026, 1, 1);
    final waits = <Duration>[];
    var initialized = false;
    final startup = StartupCoordinator(
      initialize: () async {
        initialized = true;
      },
      clock: () => clock,
      wait: (duration) async {
        waits.add(duration);
        clock = clock.add(duration);
      },
    );

    await startup.start();

    expect(initialized, isTrue);
    expect(waits, [const Duration(seconds: 3)]);
    expect(startup.completed, isTrue);
    expect(startup.errorMessage, isNull);
  });

  test('concurrent start requests share one initialization', () async {
    var initializeCount = 0;
    var clock = DateTime.utc(2026, 1, 1);
    final startup = StartupCoordinator(
      initialize: () async {
        initializeCount++;
      },
      clock: () => clock,
      wait: (duration) async {
        clock = clock.add(duration);
      },
    );

    final first = startup.start();
    final second = startup.start();
    await Future.wait([first, second]);

    expect(initializeCount, 1);
    expect(startup.completed, isTrue);
  });

  test('bootstrap failure finishes with a retryable error', () async {
    var attempts = 0;
    var clock = DateTime.utc(2026, 1, 1);
    final startup = StartupCoordinator(
      initialize: () async {
        attempts++;
        if (attempts == 1) throw StateError('secure storage is unavailable');
      },
      clock: () => clock,
      wait: (duration) async {
        clock = clock.add(duration);
      },
    );

    await startup.start();
    expect(startup.completed, isTrue);
    expect(startup.running, isFalse);
    expect(startup.errorMessage, isNotNull);

    await startup.retry();
    expect(attempts, 2);
    expect(startup.completed, isTrue);
    expect(startup.errorMessage, isNull);
  });
}
