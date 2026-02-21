import 'package:flutter/material.dart';
import 'package:yandex_mapkit/yandex_mapkit.dart';

/// Simple test screen to verify Yandex MapKit is working
class SimpleMapTest extends StatefulWidget {
  const SimpleMapTest({super.key});

  @override
  State<SimpleMapTest> createState() => _SimpleMapTestState();
}

class _SimpleMapTestState extends State<SimpleMapTest> {
  late YandexMapController _mapController;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Test Carte Yandex')),
      body: YandexMap(
        onMapCreated: (controller) {
          _mapController = controller;

          // Center on Paris as default
          _mapController.moveCamera(
            CameraUpdate.newCameraPosition(
              const CameraPosition(
                target: Point(latitude: 48.8566, longitude: 2.3522),
                zoom: 12.0,
              ),
            ),
          );
        },
      ),
    );
  }
}
