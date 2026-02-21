import 'package:flutter/material.dart';
import 'map_markers.dart';

class MapMarkersExample extends StatelessWidget {
  const MapMarkersExample({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Map Markers Example')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'User Position Marker',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Center(child: MapMarkers.userPosition()),
            const SizedBox(height: 32),

            const Text(
              'Artisan Markers',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Column(
                  children: [
                    const Text('Regular Artisan'),
                    const SizedBox(height: 8),
                    MapMarkers.artisanMarker(),
                  ],
                ),
                Column(
                  children: [
                    const Text('Nearby Artisan'),
                    const SizedBox(height: 8),
                    MapMarkers.artisanMarker(isNearby: true),
                  ],
                ),
                Column(
                  children: [
                    const Text('Rated Artisan'),
                    const SizedBox(height: 8),
                    MapMarkers.artisanMarker(rating: 5),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 32),

            const Text(
              'SVG Markers',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Column(
                  children: [
                    const Text('User Position'),
                    const SizedBox(height: 8),
                    MapMarkers.userPositionSvg(),
                  ],
                ),
                Column(
                  children: [
                    const Text('Artisan SVG'),
                    const SizedBox(height: 8),
                    MapMarkers.artisanMarkerSvg(),
                  ],
                ),
                Column(
                  children: [
                    const Text('Nearby Artisan SVG'),
                    const SizedBox(height: 8),
                    MapMarkers.artisanMarkerSvg(isNearby: true),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 32),

            const Text(
              'Cluster Marker',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Column(
                  children: [
                    const Text('Small Cluster'),
                    const SizedBox(height: 8),
                    MapMarkers.clusterMarker(count: 5),
                  ],
                ),
                Column(
                  children: [
                    const Text('Large Cluster'),
                    const SizedBox(height: 8),
                    MapMarkers.clusterMarker(count: 25),
                  ],
                ),
                Column(
                  children: [
                    const Text('Large Group'),
                    const SizedBox(height: 8),
                    MapMarkers.clusterMarker(count: 150),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 32),

            const Text(
              'Category Markers',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                MapMarkers.categoryMarker(icon: Icons.build),
                MapMarkers.categoryMarker(icon: Icons.electrical_services),
                MapMarkers.categoryMarker(icon: Icons.plumbing),
                MapMarkers.categoryMarker(icon: Icons.construction),
              ],
            ),
            const SizedBox(height: 32),

            const Text(
              'Image Marker Example',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Center(
              child: MapMarkers.imageMarker(
                imageUrl: 'https://via.placeholder.com/150',
                size: 60,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// Example usage in a map
class MapWithMarkers extends StatelessWidget {
  const MapWithMarkers({super.key});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        // Map background would go here
        // For example: GoogleMap or any other map widget

        // User position marker
        Positioned(left: 100, top: 100, child: MapMarkers.userPosition()),

        // Artisan markers
        Positioned(left: 200, top: 150, child: MapMarkers.artisanMarker()),

        // Nearby artisan marker
        Positioned(
          left: 300,
          top: 200,
          child: MapMarkers.artisanMarker(isNearby: true),
        ),

        // Cluster marker for grouped artisans
        Positioned(
          left: 150,
          top: 300,
          child: MapMarkers.clusterMarker(count: 8),
        ),
      ],
    );
  }
}
