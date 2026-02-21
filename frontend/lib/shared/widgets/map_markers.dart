import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../core/theme/app_colors.dart';

/// Custom map markers for different types of locations
class MapMarkers {
  /// User position marker with pulsing animation
  static Widget userPosition({double size = 64.0}) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          // Pulsing circles
          Positioned.fill(
            child: Align(
              alignment: Alignment.center,
              child: Container(
                width: size,
                height: size,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.lightAccentPrimary.withValues(alpha: 0.1),
                ),
              ),
            ),
          ),
          // Main marker
          Positioned.fill(
            child: Align(
              alignment: Alignment.center,
              child: Container(
                width: size * 0.5,
                height: size * 0.5,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.lightAccentPrimary,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.lightAccentPrimary.withValues(
                        alpha: 0.5,
                      ),
                      blurRadius: 8,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: Center(
                  child: Container(
                    width: size * 0.2,
                    height: size * 0.2,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Artisan marker with tool icon
  static Widget artisanMarker({
    double size = 48.0,
    bool isNearby = false,
    int? rating,
  }) {
    final color = isNearby ? AppColors.warning : AppColors.success;
    final badgeColor = isNearby ? AppColors.error : AppColors.info;

    return SizedBox(
      width: size,
      height: size * 1.33, // 48x64 ratio
      child: Stack(
        children: [
          // Pin shadow
          Positioned(
            bottom: 0,
            left: size * 0.33,
            child: Container(
              width: size * 0.33,
              height: size * 0.17,
              decoration: BoxDecoration(
                shape: BoxShape.rectangle,
                borderRadius: BorderRadius.circular(size * 0.08),
                color: Colors.black.withValues(alpha: 0.2),
              ),
            ),
          ),
          // Pin body
          Positioned.fill(
            child: Align(
              alignment: Alignment.topCenter,
              child: Container(
                width: size * 0.75,
                height: size * 0.9,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(size * 0.375),
                    topRight: Radius.circular(size * 0.375),
                    bottomLeft: Radius.circular(size * 0.1),
                    bottomRight: Radius.circular(size * 0.1),
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: color.withValues(alpha: 0.5),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Stack(
                  children: [
                    // Tool icon
                    Positioned.fill(
                      child: Align(
                        alignment: Alignment.center,
                        child: Icon(
                          Icons.build,
                          color: Colors.white,
                          size: size * 0.4,
                        ),
                      ),
                    ),
                    // Distance badge for nearby artisans
                    if (isNearby)
                      Positioned(
                        top: -size * 0.1,
                        right: -size * 0.1,
                        child: Container(
                          width: size * 0.4,
                          height: size * 0.4,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: badgeColor,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                          child: Center(
                            child: Text(
                              '2km',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: size * 0.12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                      ),
                    // Rating badge
                    if (rating != null && rating >= 4)
                      Positioned(
                        top: -size * 0.1,
                        left: -size * 0.1,
                        child: Container(
                          width: size * 0.4,
                          height: size * 0.4,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: AppColors.starRating,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                          child: Center(
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.star,
                                  color: Colors.white,
                                  size: size * 0.12,
                                ),
                                Text(
                                  rating.toString(),
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: size * 0.12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
          // Pin tip
          Positioned(
            bottom: size * 0.1,
            left: size * 0.5 - size * 0.04,
            child: Container(
              width: size * 0.08,
              height: size * 0.08,
              decoration: BoxDecoration(shape: BoxShape.circle, color: color),
            ),
          ),
        ],
      ),
    );
  }

  /// Cluster marker for grouped artisans
  static Widget clusterMarker({required int count, double size = 40.0}) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.clusterMarker,
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.3),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Center(
        child: Text(
          count > 99 ? '99+' : count.toString(),
          style: TextStyle(
            color: Colors.white,
            fontSize: size * 0.3,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  /// SVG-based markers
  static Widget svgMarker({
    required String assetPath,
    double size = 48.0,
    Color? color,
  }) {
    return SizedBox(
      width: size,
      height: size,
      child: SvgPicture.asset(
        assetPath,
        width: size,
        height: size,
        colorFilter: color != null
            ? ColorFilter.mode(color, BlendMode.srcIn)
            : null,
      ),
    );
  }

  /// Predefined SVG markers
  static Widget userPositionSvg({double size = 64.0}) {
    return svgMarker(assetPath: 'assets/markers/user-position.svg', size: size);
  }

  static Widget artisanMarkerSvg({double size = 48.0, bool isNearby = false}) {
    return svgMarker(
      assetPath: isNearby
          ? 'assets/markers/nearby-artisan.svg'
          : 'assets/markers/artisan-marker.svg',
      size: size,
    );
  }

  /// Custom marker with image
  static Widget imageMarker({
    required String imageUrl,
    double size = 48.0,
    bool hasBorder = true,
  }) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: hasBorder
            ? Border.all(color: AppColors.lightAccentPrimary, width: 2)
            : null,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.2),
            blurRadius: 6,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: ClipOval(
        child: Image.network(
          imageUrl,
          width: size,
          height: size,
          fit: BoxFit.cover,
          errorBuilder: (context, error, stackTrace) {
            return Container(
              color: AppColors.lightBackground,
              child: Icon(
                Icons.person,
                color: AppColors.lightTextSecondary,
                size: size * 0.6,
              ),
            );
          },
        ),
      ),
    );
  }

  /// Marker with category icon
  static Widget categoryMarker({
    required IconData icon,
    Color color = AppColors.lightAccentPrimary,
    double size = 40.0,
  }) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color,
        border: Border.all(color: Colors.white, width: 2),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.2),
            blurRadius: 6,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Center(
        child: Icon(icon, color: Colors.white, size: size * 0.5),
      ),
    );
  }
}
