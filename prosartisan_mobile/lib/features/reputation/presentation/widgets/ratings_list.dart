import 'package:flutter/material.dart';
import '../../domain/models/rating.dart';
import 'star_rating_widget.dart';

class RatingsList extends StatelessWidget {
  final List<Rating> ratings;
  final VoidCallback? onLoadMore;

  const RatingsList({Key? key, required this.ratings, this.onLoadMore})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (ratings.isEmpty) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.star_border, size: 64, color: Colors.grey),
              SizedBox(height: 16),
              Text(
                'Aucun avis client disponible',
                style: TextStyle(fontSize: 16, color: Colors.grey),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: ratings.length + (onLoadMore != null ? 1 : 0),
      itemBuilder: (context, index) {
        if (index == ratings.length) {
          return _buildLoadMoreButton();
        }

        final rating = ratings[index];
        return _buildRatingCard(rating);
      },
    );
  }

  Widget _buildRatingCard(Rating rating) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                StarRatingWidget(rating: rating.rating, size: 20),
                const Spacer(),
                Text(
                  _formatDate(rating.createdAt),
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
              ],
            ),
            if (rating.comment != null && rating.comment!.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                rating.comment!,
                style: const TextStyle(fontSize: 14, height: 1.4),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.person, size: 16, color: Colors.grey),
                const SizedBox(width: 4),
                Text(
                  'Client vérifié',
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLoadMoreButton() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Center(
        child: ElevatedButton(
          onPressed: onLoadMore,
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.blue[600],
            foregroundColor: Colors.white,
          ),
          child: const Text('Charger plus d\'avis'),
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}
