import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/search.dart' as ysearch;

class LocationPickerController extends GetxController {
  static const double _kAbidjanLat = 5.3484;
  static const double _kAbidjanLng = -4.0169;
  static const double _kDefaultZoom = 16.0;
  static const double _kSearchWindowDelta = 0.12;

  final TextEditingController searchController = TextEditingController();
  final ysearch.SearchManager _searchManager =
      ysearch.SearchFactory.instance.createSearchManager(
    ysearch.SearchManagerType.Online,
  );

  late final ysearch.SearchSuggestSession _suggestSession =
      _searchManager.createSuggestSession();

  final selectedLocation = Rxn<mk.Point>();
  final address = 'Adresse non résolue'.obs;
  final isLoading = false.obs;
  final isSearching = false.obs;
  final searchQuery = ''.obs;
  final suggestions = <LocationSuggestion>[].obs;

  final List<Object> _listenerRetainBag = [];
  mk.MapWindow? _mapWindow;
  _MapTapListener? _mapTapListener;
  ysearch.SearchSession? _reverseSearchSession;
  ysearch.SearchSession? _forwardSearchSession;
  Timer? _suggestDebounce;

  mk.Point get defaultPoint => const mk.Point(
        latitude: _kAbidjanLat,
        longitude: _kAbidjanLng,
      );

  @override
  void onInit() {
    super.onInit();
    loadCurrentLocation();
  }

  @override
  void onClose() {
    _suggestDebounce?.cancel();
    _reverseSearchSession?.cancel();
    _forwardSearchSession?.cancel();
    _suggestSession.reset();
    _listenerRetainBag.clear();
    searchController.dispose();
    super.onClose();
  }

  Future<void> loadCurrentLocation() async {
    try {
      isLoading.value = true;

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );

      final point = mk.Point(
        latitude: position.latitude,
        longitude: position.longitude,
      );

      await selectLocation(
        point,
        moveCamera: true,
        presetAddress: 'Recherche de l\'adresse...',
      );
    } catch (_) {
      await selectLocation(
        defaultPoint,
        moveCamera: true,
        presetAddress: 'Abidjan, Côte d\'Ivoire',
      );
    } finally {
      isLoading.value = false;
    }
  }

  void onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;

    final target = selectedLocation.value ?? defaultPoint;
    moveCamera(
      target.latitude,
      target.longitude,
      animated: false,
    );

    _mapTapListener = _MapTapListener((point) {
      clearSuggestions();
      selectLocation(point, moveCamera: true);
    });
    mapWindow.map.addInputListener(_mapTapListener!);
    _retainListener(_mapTapListener!);
  }

  void moveCamera(double lat, double lng, {bool animated = true}) {
    final mapWindow = _mapWindow;
    if (mapWindow == null) return;

    mapWindow.map.move(
      mk.CameraPosition(
        mk.Point(latitude: lat, longitude: lng),
        zoom: _kDefaultZoom,
        azimuth: 0.0,
        tilt: 0.0,
      ),
      animation: animated
          ? const mk.Animation(
              type: mk.AnimationType.Smooth,
              duration: 0.8,
            )
          : null,
    );
  }

  Future<void> selectLocation(
    mk.Point point, {
    bool moveCamera = false,
    String? presetAddress,
  }) async {
    selectedLocation.value = point;
    suggestions.clear();

    if (presetAddress != null) {
      address.value = presetAddress;
    }

    if (moveCamera) {
      this.moveCamera(point.latitude, point.longitude);
    }

    await reverseGeocode(point);
  }

  Future<void> reverseGeocode(mk.Point point) async {
    _reverseSearchSession?.cancel();
    isLoading.value = true;

    final listener = ysearch.SearchSessionSearchListener(
      onSearchResponse: (response) {
        final resolved = _extractBestResult(response);
        if (isClosed) return;

        address.value = resolved?.label ?? formatCoordinates(point);
        isLoading.value = false;
      },
      onSearchError: (_) {
        if (isClosed) return;

        address.value = formatCoordinates(point);
        isLoading.value = false;
      },
    );

    _retainListener(listener);
    _reverseSearchSession = _searchManager.submitPoint(
      point,
      ysearch.SearchOptions(
        searchTypes: ysearch.SearchType.Geo | ysearch.SearchType.Biz,
        geometry: true,
        resultPageSize: 1,
        userPosition: point,
      ),
      listener,
      zoom:
          _mapWindow?.map.cameraPosition.zoom.round() ?? _kDefaultZoom.round(),
    );
  }

  void onSearchChanged(String rawValue) {
    final query = rawValue.trim();
    searchQuery.value = query;
    _suggestDebounce?.cancel();

    if (query.isEmpty) {
      suggestions.clear();
      isSearching.value = false;
      return;
    }

    isSearching.value = true;
    _suggestDebounce = Timer(
      const Duration(milliseconds: 350),
      () => loadSuggestions(query),
    );
  }

  void onSearchFieldTapped() {
    if (searchQuery.value.isNotEmpty) {
      onSearchChanged(searchQuery.value);
    }
  }

  void loadSuggestions(String query) {
    final listener = ysearch.SearchSuggestSessionSuggestListener(
      onResponse: (suggest) {
        final results = suggest.items
            .map((item) {
              final displayText = item.displayText?.trim();
              return LocationSuggestion(
                title: displayText != null && displayText.isNotEmpty
                    ? displayText
                    : item.title.text.trim(),
                subtitle: item.subtitle?.text.trim(),
                point: item.center,
                searchText: item.searchText,
              );
            })
            .where((item) => item.title.isNotEmpty)
            .take(6)
            .toList();

        if (isClosed) return;
        suggestions.assignAll(results);
        isSearching.value = false;
      },
      onError: (_) {
        if (isClosed) return;
        suggestions.clear();
        isSearching.value = false;
      },
    );

    _retainListener(listener);
    _suggestSession.suggest(
      buildSearchWindow(selectedLocation.value ?? defaultPoint),
      ysearch.SuggestOptions(
        suggestTypes: ysearch.SuggestType.Geo | ysearch.SuggestType.Biz,
        userPosition: selectedLocation.value ?? defaultPoint,
      ),
      listener,
      text: query,
    );
  }

  Future<void> selectSuggestion(LocationSuggestion suggestion) async {
    searchController.text = suggestion.title;
    searchQuery.value = suggestion.title;
    clearSuggestions();

    if (suggestion.point != null) {
      await selectLocation(
        suggestion.point!,
        moveCamera: true,
        presetAddress: suggestion.fullLabel,
      );
      return;
    }

    await searchByText(suggestion.searchText);
  }

  Future<void> searchByText(String query) async {
    _forwardSearchSession?.cancel();
    isLoading.value = true;

    final listener = ysearch.SearchSessionSearchListener(
      onSearchResponse: (response) async {
        final resolved = _extractBestResult(response);
        if (resolved?.point == null) {
          if (isClosed) return;
          isLoading.value = false;
          return;
        }

        await selectLocation(
          resolved!.point!,
          moveCamera: true,
          presetAddress: resolved.label,
        );
      },
      onSearchError: (_) {
        if (isClosed) return;
        isLoading.value = false;
      },
    );

    _retainListener(listener);
    _forwardSearchSession = _searchManager.submit(
      mk.Geometry.fromBoundingBox(
        buildSearchWindow(selectedLocation.value ?? defaultPoint),
      ),
      ysearch.SearchOptions(
        searchTypes: ysearch.SearchType.Geo | ysearch.SearchType.Biz,
        geometry: true,
        resultPageSize: 1,
        userPosition: selectedLocation.value ?? defaultPoint,
      ),
      listener,
      text: query,
    );
  }

  void clearSearch() {
    searchController.clear();
    searchQuery.value = '';
    clearSuggestions();
  }

  void clearSuggestions() {
    suggestions.clear();
  }

  void confirmLocation() {
    final location = selectedLocation.value;
    if (location == null) return;

    Get.back(result: {
      'latitude': location.latitude,
      'longitude': location.longitude,
      'address': address.value,
    });
  }

  String formatCoordinates(mk.Point point) {
    return 'Lat: ${point.latitude.toStringAsFixed(5)}, Lng: ${point.longitude.toStringAsFixed(5)}';
  }

  mk.BoundingBox buildSearchWindow(mk.Point center) {
    return mk.BoundingBox(
      mk.Point(
        latitude: center.latitude - _kSearchWindowDelta,
        longitude: center.longitude - _kSearchWindowDelta,
      ),
      mk.Point(
        latitude: center.latitude + _kSearchWindowDelta,
        longitude: center.longitude + _kSearchWindowDelta,
      ),
    );
  }

  _ResolvedLocationResult? _extractBestResult(ysearch.SearchResponse response) {
    final geoObject = _findFirstGeoObject(response.collection);
    if (geoObject == null) return null;

    final business = geoObject.metadataContainer
        .get(ysearch.SearchBusinessObjectMetadata.factory);
    if (business != null) {
      final address = business.address.formattedAddress.trim();
      final title = business.name.trim();
      final label = title.isNotEmpty && address.isNotEmpty
          ? '$title, $address'
          : (address.isNotEmpty
              ? address
              : (title.isNotEmpty ? title : _fallbackGeoLabel(geoObject)));

      return _ResolvedLocationResult(
        label: label,
        point: _extractPointFromGeoObject(geoObject),
      );
    }

    final toponym = geoObject.metadataContainer
        .get(ysearch.SearchToponymObjectMetadata.factory);
    if (toponym != null) {
      final address = toponym.address.formattedAddress.trim();
      return _ResolvedLocationResult(
        label: address.isNotEmpty ? address : _fallbackGeoLabel(geoObject),
        point: toponym.balloonPoint,
      );
    }

    return _ResolvedLocationResult(
      label: _fallbackGeoLabel(geoObject),
      point: _extractPointFromGeoObject(geoObject),
    );
  }

  mk.GeoObject? _findFirstGeoObject(mk.GeoObjectCollection collection) {
    for (final child in collection.children) {
      final geoObject = child.asGeoObject();
      if (geoObject != null) {
        return geoObject;
      }

      final nested = child.asGeoObjectCollection();
      if (nested != null) {
        final resolved = _findFirstGeoObject(nested);
        if (resolved != null) {
          return resolved;
        }
      }
    }

    return null;
  }

  mk.Point? _extractPointFromGeoObject(mk.GeoObject geoObject) {
    for (final geometry in geoObject.geometry) {
      final point = geometry.asPoint();
      if (point != null) {
        return point;
      }

      final box = geometry.asBoundingBox();
      if (box != null) {
        return _centerFromBoundingBox(box);
      }
    }

    final boundingBox = geoObject.boundingBox;
    if (boundingBox != null) {
      return _centerFromBoundingBox(boundingBox);
    }

    return null;
  }

  mk.Point _centerFromBoundingBox(mk.BoundingBox box) {
    return mk.Point(
      latitude: (box.southWest.latitude + box.northEast.latitude) / 2,
      longitude: (box.southWest.longitude + box.northEast.longitude) / 2,
    );
  }

  String _fallbackGeoLabel(mk.GeoObject geoObject) {
    final name = geoObject.name?.trim();
    if (name != null && name.isNotEmpty) {
      return name;
    }

    final description = geoObject.descriptionText?.trim();
    if (description != null && description.isNotEmpty) {
      return description;
    }

    return selectedLocation.value != null
        ? formatCoordinates(selectedLocation.value!)
        : 'Adresse non trouvée';
  }

  void _retainListener(Object listener) {
    _listenerRetainBag.add(listener);
    if (_listenerRetainBag.length > 8) {
      _listenerRetainBag.removeRange(0, _listenerRetainBag.length - 8);
    }
  }
}

class LocationSuggestion {
  final String title;
  final String? subtitle;
  final mk.Point? point;
  final String searchText;

  const LocationSuggestion({
    required this.title,
    required this.subtitle,
    required this.point,
    required this.searchText,
  });

  String get fullLabel {
    final extra = subtitle?.trim();
    if (extra == null || extra.isEmpty) {
      return title;
    }
    return '$title, $extra';
  }
}

class _ResolvedLocationResult {
  final String label;
  final mk.Point? point;

  const _ResolvedLocationResult({
    required this.label,
    required this.point,
  });
}

class _MapTapListener extends mk.MapInputListener {
  final void Function(mk.Point) onTap;

  _MapTapListener(this.onTap);

  @override
  void onMapTap(mk.Map map, mk.Point point) {
    onTap(point);
  }

  @override
  void onMapLongTap(mk.Map map, mk.Point point) {}
}
