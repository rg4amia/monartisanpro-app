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

  String get shortLabel => (label != null && label!.trim().isNotEmpty)
      ? label!.trim()
      : addressLine;

  factory AddressModel.fromJson(Map<String, dynamic> json) {
    final location = json['location'];
    final locationMap = location is Map ? location : null;

    return AddressModel(
      id: json['id'] is int
          ? json['id'] as int
          : int.tryParse('${json['id']}') ?? 0,
      label: json['label'] is String ? json['label'] as String : null,
      recipientName: json['recipientName'] is String
          ? json['recipientName'] as String
          : '',
      recipientPhone: json['recipientPhone'] is String
          ? json['recipientPhone'] as String
          : '',
      addressLine:
          json['addressLine'] is String ? json['addressLine'] as String : '',
      city: json['city'] is String ? json['city'] as String : '',
      region: json['region'] is String ? json['region'] as String : null,
      country: json['country'] is String ? json['country'] as String : null,
      isDefault: json['isDefault'] == true,
      lat: locationMap != null && locationMap['lat'] is num
          ? (locationMap['lat'] as num).toDouble()
          : null,
      lng: locationMap != null && locationMap['lng'] is num
          ? (locationMap['lng'] as num).toDouble()
          : null,
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
