import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Generates custom marker icons from Flutter widgets
class MarkerIconGenerator {
  /// Creates a user position marker icon
  static Future<Uint8List> createUserMarker({double size = 72}) async {
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final paint = Paint()..isAntiAlias = true;

    // Draw shadow
    final shadowPaint = Paint()
      ..color = AppColors.lightAccentPrimary.withValues(alpha: 0.3)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 8);
    canvas.drawCircle(Offset(size / 2, size / 2 + 2), size / 2, shadowPaint);

    // Draw outer circle (white border)
    paint.color = Colors.white;
    canvas.drawCircle(Offset(size / 2, size / 2), size / 2, paint);

    // Draw inner circle (blue)
    paint.color = AppColors.lightAccentPrimary;
    canvas.drawCircle(Offset(size / 2, size / 2), size / 2 - 3, paint);

    // Draw person icon
    final iconPainter = TextPainter(
      text: TextSpan(
        text: String.fromCharCode(Icons.person.codePoint),
        style: TextStyle(
          fontSize: size * 0.5,
          fontFamily: Icons.person.fontFamily,
          color: Colors.white,
        ),
      ),
      textDirection: TextDirection.ltr,
    );
    iconPainter.layout();
    iconPainter.paint(
      canvas,
      Offset((size - iconPainter.width) / 2, (size - iconPainter.height) / 2),
    );

    final picture = recorder.endRecording();
    final img = await picture.toImage(size.toInt(), size.toInt());
    final byteData = await img.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

  /// Creates an artisan marker icon (pin shape)
  static Future<Uint8List> createArtisanMarker({
    required bool isNearby,
    double size = 60,
  }) async {
    final color = isNearby ? AppColors.goldenMarker : AppColors.blueMarker;
    final height = size + 12; // Extra height for pointer
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final paint = Paint()..isAntiAlias = true;

    // Draw shadow
    final shadowPath = _createPinPath(size, height);
    final shadowPaint = Paint()
      ..color = Colors.black.withValues(alpha: 0.3)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4);
    canvas.save();
    canvas.translate(0, 2);
    canvas.drawPath(shadowPath, shadowPaint);
    canvas.restore();

    // Draw pin path
    final pinPath = _createPinPath(size, height);

    // Fill pin
    paint.color = color;
    canvas.drawPath(pinPath, paint);

    // Draw white border
    paint
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    canvas.drawPath(pinPath, paint);

    // Draw white circle for icon background
    paint
      ..color = Colors.white
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size / 2, size / 2), size * 0.35, paint);

    // Draw construction icon
    final iconPainter = TextPainter(
      text: TextSpan(
        text: String.fromCharCode(Icons.construction.codePoint),
        style: TextStyle(
          fontSize: size * 0.5,
          fontFamily: Icons.construction.fontFamily,
          color: color,
        ),
      ),
      textDirection: TextDirection.ltr,
    );
    iconPainter.layout();
    iconPainter.paint(
      canvas,
      Offset(
        (size - iconPainter.width) / 2,
        (size - iconPainter.height) / 2 - 2,
      ),
    );

    final picture = recorder.endRecording();
    final img = await picture.toImage(size.toInt(), height.toInt());
    final byteData = await img.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

  /// Creates a cluster marker icon
  static Future<Uint8List> createClusterMarker({
    required int count,
    double size = 72,
  }) async {
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final paint = Paint()..isAntiAlias = true;

    // Draw shadow
    final shadowPaint = Paint()
      ..color = Colors.black.withValues(alpha: 0.3)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 8);
    canvas.drawCircle(Offset(size / 2, size / 2 + 2), size / 2, shadowPaint);

    // Draw outer circle (white border)
    paint.color = Colors.white;
    canvas.drawCircle(Offset(size / 2, size / 2), size / 2, paint);

    // Draw gradient circle
    final gradient = ui.Gradient.linear(Offset(0, 0), Offset(size, size), [
      AppColors.clusterMarker,
      AppColors.clusterMarker.withValues(alpha: 0.8),
    ]);
    paint
      ..shader = gradient
      ..color = AppColors.clusterMarker;
    canvas.drawCircle(Offset(size / 2, size / 2), size / 2 - 3, paint);

    // Draw count text
    final textPainter = TextPainter(
      text: TextSpan(
        text: count > 99 ? '99+' : count.toString(),
        style: TextStyle(
          color: Colors.white,
          fontSize: size * 0.35,
          fontWeight: FontWeight.bold,
        ),
      ),
      textDirection: TextDirection.ltr,
    );
    textPainter.layout();
    textPainter.paint(
      canvas,
      Offset((size - textPainter.width) / 2, (size - textPainter.height) / 2),
    );

    final picture = recorder.endRecording();
    final img = await picture.toImage(size.toInt(), size.toInt());
    final byteData = await img.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

  /// Helper method to create pin path
  static Path _createPinPath(double width, double height) {
    final path = Path();
    final radius = width / 2;
    final center = Offset(width / 2, radius);

    // Circle part
    path.addOval(Rect.fromCircle(center: center, radius: radius));

    // Pointer part (triangle at bottom)
    path.moveTo(width / 2 - radius * 0.3, radius * 1.5);
    path.lineTo(width / 2, height - 2);
    path.lineTo(width / 2 + radius * 0.3, radius * 1.5);
    path.close();

    return path;
  }
}
