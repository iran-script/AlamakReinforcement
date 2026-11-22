import 'package:flutter/material.dart';

/// A small, scalable mark combining a map pin with a connected gas network.
class AlamakBrandMark extends StatelessWidget {
  const AlamakBrandMark({super.key, this.size = 88});

  final double size;

  @override
  Widget build(BuildContext context) => SizedBox.square(
    dimension: size,
    child: CustomPaint(painter: _AlamakBrandPainter()),
  );
}

class _AlamakBrandPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final scale = size.width / 108;
    canvas.save();
    canvas.scale(scale);

    final background = Paint()..color = const Color(0xff0b1d35);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(0, 0, 108, 108),
        const Radius.circular(26),
      ),
      background,
    );
    canvas.drawCircle(
      const Offset(54, 54),
      40,
      Paint()..color = const Color(0xff123456),
    );

    final network = Paint()
      ..color = const Color(0xff54d7e5)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final path = Path()
      ..moveTo(28, 76)
      ..lineTo(80, 76)
      ..moveTo(36, 76)
      ..lineTo(36, 62)
      ..lineTo(44, 54)
      ..moveTo(72, 76)
      ..lineTo(72, 62)
      ..lineTo(64, 54)
      ..moveTo(54, 49)
      ..lineTo(54, 76);
    canvas.drawPath(path, network);

    final pin = Path()
      ..moveTo(54, 17)
      ..cubicTo(43, 17, 36, 25, 36, 35)
      ..cubicTo(36, 47, 54, 59, 54, 59)
      ..cubicTo(54, 59, 72, 47, 72, 35)
      ..cubicTo(72, 25, 65, 17, 54, 17)
      ..close();
    canvas.drawPath(
      pin,
      Paint()
        ..color = const Color(0xfff5fbff)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 4,
    );
    canvas.drawCircle(
      const Offset(54, 35),
      5,
      Paint()..color = const Color(0xff54d7e5),
    );

    final node = Paint()..color = const Color(0xfff5fbff);
    canvas.drawCircle(const Offset(28, 76), 4, node);
    canvas.drawCircle(const Offset(80, 76), 4, node);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
