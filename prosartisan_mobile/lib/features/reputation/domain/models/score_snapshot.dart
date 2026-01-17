class ScoreSnapshot {
  final int score;
  final String reason;
  final DateTime recordedAt;

  const ScoreSnapshot({
    required this.score,
    required this.reason,
    required this.recordedAt,
  });

  factory ScoreSnapshot.fromJson(Map<String, dynamic> json) {
    return ScoreSnapshot(
      score: json['score'],
      reason: json['reason'],
      recordedAt: DateTime.parse(json['recorded_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'score': score,
      'reason': reason,
      'recorded_at': recordedAt.toIso8601String(),
    };
  }
}
