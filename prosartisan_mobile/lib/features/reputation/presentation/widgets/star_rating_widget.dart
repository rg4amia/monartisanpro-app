import 'package:flutter/material.dart';

class StarRatingWidget extends StatelessWidget {
  final int rating;
  final int maxRating;
  final double size;
  final Color activeColor;
  final Color inactiveColor;
  final Function(int)? onRatingChanged;

  const StarRatingWidget({
    Key? key,
    required this.rating,
    this.maxRating = 5,
    this.size = 24,
    this.activeColor = Colors.amber,
    this.inactiveColor = Colors.grey,
    this.onRatingChanged,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(maxRating, (index) {
        return GestureDetector(
          onTap: onRatingChanged != null
              ? () => onRatingChanged!(index + 1)
              : null,
          child: Icon(
            index < rating ? Icons.star : Icons.star_border,
            size: size,
            color: index < rating ? activeColor : inactiveColor,
          ),
        );
      }),
    );
  }
}
