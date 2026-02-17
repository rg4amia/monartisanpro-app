import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

class RatingStars extends StatefulWidget {
  final double rating;
  final int maxStars;
  final double size;
  final bool interactive;
  final ValueChanged<double>? onRatingChanged;
  final Color? activeColor;
  final Color? inactiveColor;

  const RatingStars({
    super.key,
    required this.rating,
    this.maxStars = 5,
    this.size = 24,
    this.interactive = false,
    this.onRatingChanged,
    this.activeColor,
    this.inactiveColor,
  });

  @override
  State<RatingStars> createState() => _RatingStarsState();
}

class _RatingStarsState extends State<RatingStars> {
  late double _currentRating;

  @override
  void initState() {
    super.initState();
    _currentRating = widget.rating;
  }

  @override
  void didUpdateWidget(RatingStars oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.rating != widget.rating) {
      _currentRating = widget.rating;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(widget.maxStars, (index) {
        return GestureDetector(
          onTap: widget.interactive
              ? () {
                  setState(() {
                    _currentRating = index + 1.0;
                  });
                  widget.onRatingChanged?.call(_currentRating);
                }
              : null,
          child: Icon(
            _getStarIcon(index),
            size: widget.size,
            color: _getStarColor(index),
          ),
        );
      }),
    );
  }

  IconData _getStarIcon(int index) {
    final difference = _currentRating - index;

    if (difference >= 1.0) {
      return Icons.star; // Full star
    } else if (difference >= 0.5) {
      return Icons.star_half; // Half star
    } else {
      return Icons.star_border; // Empty star
    }
  }

  Color _getStarColor(int index) {
    final difference = _currentRating - index;

    if (difference >= 0.5) {
      return widget.activeColor ?? AppColors.starRating;
    } else {
      return widget.inactiveColor ?? AppColors.lightTextTertiary;
    }
  }
}
