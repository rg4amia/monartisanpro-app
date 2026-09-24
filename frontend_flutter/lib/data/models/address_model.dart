import 'package:frontend_flutter/core/utils/json_readers.dart';

class AddressModel {
  final int id;
  final String? label;
  final String recipientName;
  final String recipientPhone;
  final String addressLine;
  final String city;
  final String? region;
  final String? country;
  final bool isDefault;
  final double? lat;
  final double? lng;

  const AddressModel({
    required this.id,
    this.label,
    required this.recipientName,
    required this.recipientPhone,
    required this.addressLine,
    required this.city,
    this.region,
    this.country,
    this.isDefault = false,
    this.lat,
    this.lng,
  });

  String get shortLabel =>
      (label != null && label!.trim().isNotEmpty) ? label!.trim() : addressLine;

  factory AddressModel.fromJson(Map<String, dynamic> json) {
    final location = json['location'];
    final locationMap = location is Map ? location : null;

    return AddressModel(
      id: readInt(json['id']) ?? 0,
      label: json['label'] is String ? json['label'] as String : null,
      recipientName: readString(json['recipientName']) ?? '',
      recipientPhone: readString(json['recipientPhone']) ?? '',
      addressLine:
          json['addressLine'] is String ? json['addressLine'] as String : '',
      city: json['city'] is String ? json['city'] as String : '',
      region: json['region'] is String ? json['region'] as String : null,
      country: json['country'] is String ? json['country'] as String : null,
      isDefault: json['isDefault'] == true,
      lat: readDouble(locationMap?['lat']),
      lng: readDouble(locationMap?['lng']),
    );
  }

  Map<String, dynamic> toRequestJson() => {
        if (label != null && label!.trim().isNotEmpty) 'label': label,
        'recipient_name': recipientName,
        'recipient_phone': recipientPhone,
        'address_line': addressLine,
        'city': city,
        if (region != null && region!.trim().isNotEmpty) 'region': region,
        if (lat != null) 'latitude': lat,
        if (lng != null) 'longitude': lng,
      };
}
