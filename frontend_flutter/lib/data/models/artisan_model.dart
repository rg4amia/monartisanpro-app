class ArtisanModel {
  final int id;
  final String phone;
  final String? name;
  final String? photoUrl;
  final String? trade;
  final String? sector;
  final int scoreNzassa;
  final double? distance;
  final bool isGoldenMarker;
  final double? lat;
  final double? lng;

  const ArtisanModel({
    required this.id,
    required this.phone,
    required this.scoreNzassa,
    required this.isGoldenMarker,
    this.name,
    this.photoUrl,
    this.trade,
    this.sector,
    this.distance,
    this.lat,
    this.lng,
  });

  factory ArtisanModel.fromJson(Map<String, dynamic> json) => ArtisanModel(
        id: json['id'] as int,
        phone: json['phone'] as String,
        name: json['name'] as String?,
        photoUrl: json['photoUrl'] as String?,
        trade: json['trade'] as String?,
        sector: json['sector'] as String?,
        scoreNzassa: json['scoreNzassa'] as int? ?? 0,
        distance: (json['distance'] as num?)?.toDouble(),
        isGoldenMarker: json['isGoldenMarker'] as bool? ?? false,
        lat: (json['lat'] as num?)?.toDouble(),
        lng: (json['lng'] as num?)?.toDouble(),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'phone': phone,
        'name': name,
        'photoUrl': photoUrl,
        'trade': trade,
        'sector': sector,
        'scoreNzassa': scoreNzassa,
        'distance': distance,
        'isGoldenMarker': isGoldenMarker,
        'lat': lat,
        'lng': lng,
      };
}
