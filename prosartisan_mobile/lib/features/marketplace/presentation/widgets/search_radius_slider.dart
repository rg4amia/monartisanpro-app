import 'package:flutter/material.dart';

class SearchRadiusSlider extends StatelessWidget {
  final double value;
  final Function(double) onChanged;
  final bool showLabel;

  const SearchRadiusSlider({
    super.key,
    required this.value,
    required this.onChanged,
    this.showLabel = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (showLabel) ...[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Rayon de recherche',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              Text(
                '${value.toStringAsFixed(1)} km',
                style: TextStyle(
                  color: Colors.blue[600],
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
        ],

        Row(
          children: [
            if (!showLabel) ...[
              const Icon(Icons.location_on, size: 16, color: Colors.grey),
              const SizedBox(width: 8),
            ],

            Expanded(
              child: SliderTheme(
                data: SliderTheme.of(context).copyWith(
                  activeTrackColor: Colors.blue[600],
                  inactiveTrackColor: Colors.blue[100],
                  thumbColor: Colors.blue[600],
                  overlayColor: Colors.blue[100],
                  trackHeight: 4,
                  thumbShape: const RoundSliderThumbShape(
                    enabledThumbRadius: 8,
                  ),
                ),
                child: Slider(
                  value: value,
                  min: 1.0,
                  max: 50.0,
                  divisions: 49,
                  onChanged: onChanged,
                ),
              ),
            ),

            if (!showLabel) ...[
              const SizedBox(width: 8),
              SizedBox(
                width: 50,
                child: Text(
                  '${value.toStringAsFixed(1)} km',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.blue[600],
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ],
        ),

        if (showLabel) ...[
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '1 km',
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              ),
              Text(
                '50 km',
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
