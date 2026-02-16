import '../../../../shared/models/provider_model.dart';

/// Model for services/missions from the API
class ServiceModel {
  final String id;
  final String title;
  final String description;
  final double price;
  final String currency;
  final String? imageUrl;
  final double rating;
  final int reviewCount;
  final ProviderModel provider;
  final String status;
  final bool isFavorite;
  final List<String> tags;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  ServiceModel({
    required this.id,
    required this.title,
    required this.description,
    required this.price,
    required this.currency,
    this.imageUrl,
    required this.rating,
    required this.reviewCount,
    required this.provider,
    this.status = 'active',
    this.isFavorite = false,
    this.tags = const [],
    this.createdAt,
    this.updatedAt,
  });

  factory ServiceModel.fromJson(Map<String, dynamic> json) {
    return ServiceModel(
      id: json['id'].toString(),
      title: json['title'] ?? json['description'] ?? 'Service',
      description: json['description'] ?? '',
      price: double.tryParse(json['budget']?.toString() ?? '0') ?? 0.0,
      currency: json['currency'] ?? 'FCFA',
      imageUrl: json['image_url'],
      rating: double.tryParse(json['rating']?.toString() ?? '0') ?? 0.0,
      reviewCount: json['review_count'] ?? 0,
      provider: ProviderModel.fromJson(json['client'] ?? json['artisan'] ?? {}),
      status: json['status'] ?? 'active',
      isFavorite: json['is_favorite'] ?? false,
      tags: List<String>.from(json['tags'] ?? []),
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'description': description,
      'price': price,
      'currency': currency,
      'image_url': imageUrl,
      'rating': rating,
      'review_count': reviewCount,
      'provider': provider.toJson(),
      'status': status,
      'is_favorite': isFavorite,
      'tags': tags,
      'created_at': createdAt?.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
    };
  }

  ServiceModel copyWith({
    String? id,
    String? title,
    String? description,
    double? price,
    String? currency,
    String? imageUrl,
    double? rating,
    int? reviewCount,
    ProviderModel? provider,
    String? status,
    bool? isFavorite,
    List<String>? tags,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return ServiceModel(
      id: id ?? this.id,
      title: title ?? this.title,
      description: description ?? this.description,
      price: price ?? this.price,
      currency: currency ?? this.currency,
      imageUrl: imageUrl ?? this.imageUrl,
      rating: rating ?? this.rating,
      reviewCount: reviewCount ?? this.reviewCount,
      provider: provider ?? this.provider,
      status: status ?? this.status,
      isFavorite: isFavorite ?? this.isFavorite,
      tags: tags ?? this.tags,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}
