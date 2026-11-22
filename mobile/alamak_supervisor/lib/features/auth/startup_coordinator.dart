import 'package:flutter/foundation.dart';

typedef StartupInitializer = Future<void> Function();
typedef StartupClock = DateTime Function();
typedef StartupWait = Future<void> Function(Duration duration);

/// Runs session bootstrap once and keeps the first Flutter screen visible long
/// enough for its identity to be read before routing into the app.
class StartupCoordinator extends ChangeNotifier {
  StartupCoordinator({
    required this.initialize,
    this.minimumDisplay = const Duration(seconds: 3),
    StartupClock? clock,
    StartupWait? wait,
  })  : _clock = clock ?? DateTime.now,
        _wait = wait ?? Future<void>.delayed;

  final StartupInitializer initialize;
  final StartupClock _clock;
  final StartupWait _wait;
  final Duration minimumDisplay;

  Future<void>? _attempt;

  bool running = false;
  bool completed = false;
  String? errorMessage;

  Future<void> start() => _attempt ??= _run();

  Future<void> retry() {
    if (running) return _attempt ?? Future<void>.value();
    _attempt = null;
    return start();
  }

  Future<void> _run() async {
    running = true;
    completed = false;
    errorMessage = null;
    final startedAt = _clock();
    notifyListeners();

    try {
      await initialize();
    } catch (_) {
      errorMessage = 'راه‌اندازی برنامه کامل نشد. دوباره تلاش کنید.';
    }

    final remaining = minimumDisplay - _clock().difference(startedAt);
    if (remaining > Duration.zero) {
      try {
        await _wait(remaining);
      } catch (_) {
        errorMessage ??= 'آماده‌سازی صفحه شروع کامل نشد. دوباره تلاش کنید.';
      }
    }

    running = false;
    completed = true;
    notifyListeners();
  }
}
