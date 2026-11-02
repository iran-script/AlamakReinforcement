import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

typedef CameraCapture = Future<XFile?> Function();
typedef GalleryCapture = Future<List<XFile>> Function();

Future<List<XFile>> chooseImages(
  BuildContext context, {
  required CameraCapture camera,
  required GalleryCapture gallery,
}) async {
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    showDragHandle: true,
    builder: (_) => const ImageSourceSheet(),
  );
  if (source == null) return const [];
  if (source == ImageSource.camera) {
    final file = await camera();
    return file == null ? const [] : [file];
  }
  return gallery();
}

class ImageSourceSheet extends StatelessWidget {
  const ImageSourceSheet({super.key});

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Text('\u0627\u0641\u0632\u0648\u062f\u0646 \u062a\u0635\u0648\u06cc\u0631', textDirection: TextDirection.rtl, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 10),
        ListTile(
          leading: const CircleAvatar(child: Icon(Icons.photo_camera_outlined)),
          title: const Text('\u06af\u0631\u0641\u062a\u0646 \u0639\u06a9\u0633 \u0628\u0627 \u062f\u0648\u0631\u0628\u06cc\u0646', textDirection: TextDirection.rtl),
          onTap: () => Navigator.pop(context, ImageSource.camera),
        ),
        ListTile(
          leading: const CircleAvatar(child: Icon(Icons.photo_library_outlined)),
          title: const Text('\u0627\u0646\u062a\u062e\u0627\u0628 \u062a\u0635\u0648\u06cc\u0631 \u0627\u0632 \u06af\u0627\u0644\u0631\u06cc', textDirection: TextDirection.rtl),
          onTap: () => Navigator.pop(context, ImageSource.gallery),
        ),
      ]),
    ),
  );
}
