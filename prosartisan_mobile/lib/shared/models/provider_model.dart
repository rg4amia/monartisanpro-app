/// Model for service providers (artisans/clients)
class ProviderModel {
  final String id;
  final String name;
  final String role;
  final bool isVerified;
  final double rating;
  final String? avatarUrl;
  final String? phoneNumber;
  final String? email;
  final String? location;

  const ProviderModel({
    required this.id,
    required this.name,
    required this.role,
    required this.isVerified,
    required this.rating,
    this.avatarUrl,
    this.phoneNumber,
    this.email,
    this.location,
  });

  factory ProviderModel.fromJson(Map<String, dynamic> json) {
    return ProviderModel(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? json['email']?.split('@').first ?? 'Utilisateur',
      role: json['role'] ?? json['user_type'] ?? 'CLIENT',
      isVerified: json['is_verified'] ?? json['kyc_verified'] ?? false,
      rating: double.tryParse(json['rating']?.toString() ?? '0') ?? 0.0,
      avatarUrl: json['avatar_url'] ?? json['profile_image'],
      phoneNumber: json['phone_number'],
      email: json['email'],
      location: json['location'] ?? json['address'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'role': role,
      'is_verified': isVerified,
      'rating': rating,
      'avatar_url': avatarUrl,
      'phone_number': phoneNumber,
      'email': email,
      'location': location,
    };
  }
}
