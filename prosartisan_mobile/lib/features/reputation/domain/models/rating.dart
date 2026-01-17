class Rating {
  final String id;
  final String missionId;
  final String clientId;
  final String artisanId;
  final int rating;
  final String? comment;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Rating({
    required this.id,
    required this.missionId,
    required this.clientId,
    required this.artisanId,
    required this.rating,
    this.comment,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Rating.fromJson(Map<String, dynamic> json) {
    return Rating(
      id: json['id'],
      missionId: json['mission_id'],
      clientId: json['client_id'],
      artisanId: json['artisan_id'],
      rating: json['rating'],
      comment: json['comment'],
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: DateTime.parse(json['updated_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'mission_id': missionId,
      'client_id': clientId,
      'artisan_id': artisanId,
      'rating': rating,
      'comment': comment,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }
}
