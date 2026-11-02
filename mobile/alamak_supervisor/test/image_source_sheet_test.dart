import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:alamak_supervisor/features/cases/image_source_sheet.dart';

void main() {
  testWidgets('offers camera and gallery choices in Persian', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: ImageSourceSheet())));
    expect(find.text('\u06af\u0631\u0641\u062a\u0646 \u0639\u06a9\u0633 \u0628\u0627 \u062f\u0648\u0631\u0628\u06cc\u0646'), findsOneWidget);
    expect(find.text('\u0627\u0646\u062a\u062e\u0627\u0628 \u062a\u0635\u0648\u06cc\u0631 \u0627\u0632 \u06af\u0627\u0644\u0631\u06cc'), findsOneWidget);
    expect(find.byIcon(Icons.photo_camera_outlined), findsOneWidget);
    expect(find.byIcon(Icons.photo_library_outlined), findsOneWidget);
  });

  testWidgets('camera selection invokes camera capture only', (tester) async {
    var cameraCalls = 0;
    var galleryCalls = 0;
    final key = GlobalKey<_HarnessState>();
    await tester.pumpWidget(MaterialApp(home: _Harness(key: key,
      camera: () async { cameraCalls++; return null; },
      gallery: () async { galleryCalls++; return []; })));
    await tester.tap(find.byKey(const Key('choose')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('\u06af\u0631\u0641\u062a\u0646 \u0639\u06a9\u0633 \u0628\u0627 \u062f\u0648\u0631\u0628\u06cc\u0646'));
    await tester.pumpAndSettle();
    expect(cameraCalls, 1);
    expect(galleryCalls, 0);
  });

  testWidgets('gallery selection invokes multi image capture', (tester) async {
    var cameraCalls = 0;
    var galleryCalls = 0;
    await tester.pumpWidget(MaterialApp(home: _Harness(
      camera: () async { cameraCalls++; return null; },
      gallery: () async { galleryCalls++; return []; })));
    await tester.tap(find.byKey(const Key('choose')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('\u0627\u0646\u062a\u062e\u0627\u0628 \u062a\u0635\u0648\u06cc\u0631 \u0627\u0632 \u06af\u0627\u0644\u0631\u06cc'));
    await tester.pumpAndSettle();
    expect(galleryCalls, 1);
    expect(cameraCalls, 0);
  });

  testWidgets('closing the chooser cancels without invoking a picker', (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(home: _Harness(
      camera: () async { calls++; return null; },
      gallery: () async { calls++; return []; })));
    await tester.tap(find.byKey(const Key('choose')));
    await tester.pumpAndSettle();
    await tester.tapAt(const Offset(10, 10));
    await tester.pumpAndSettle();
    expect(calls, 0);
  });
}

class _Harness extends StatefulWidget {
  const _Harness({super.key, required this.camera, required this.gallery});
  final CameraCapture camera;
  final GalleryCapture gallery;
  @override
  State<_Harness> createState() => _HarnessState();
}

class _HarnessState extends State<_Harness> {
  Future<void> choose() async => chooseImages(context, camera: widget.camera, gallery: widget.gallery);
  @override
  Widget build(BuildContext context) => Scaffold(body: Center(child: ElevatedButton(key: const Key('choose'), onPressed: choose, child: const Text('choose'))));
}
