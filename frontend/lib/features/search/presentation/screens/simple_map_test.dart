import 'package:flutter/material.dart';
import '../../../../core/widgets/flutter_map_widget.dart';
import '../../../../core/utils/mapkit_helper.dart';

/// Simple test screen to verify Yandex MapKit is working
class SimpleMapTest extends StatefulWidget {
  const SimpleMapTest({super.key});

  @override
  State<SimpleMapTest> createState() => _SimpleMapTestState();
}

class _SimpleMapTestState extends State<SimpleMapTest> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Test Carte Yandex')),
      body: FlutterMapWidget(
        onMapCreated: (mapWindow) {
          // Center on Paris as default
          mapWindow.map.move(
            MapKitHelper.createCameraPosition(
              latitude: 48.8566,
              longitude: 2.3522,
              zoom: 12.0,
            ),
            animation: MapKitHelper.createSmoothAnimation(),
          );
        },
      ),
    );
  }
}
