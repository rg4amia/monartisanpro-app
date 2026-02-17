import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/score_controller.dart';
import '../../../../shared/models/scoring_model.dart';
import '../../../../shared/models/project_model.dart';

class CreateReviewScreen extends StatefulWidget {
  final Project project;

  const CreateReviewScreen({super.key, required this.project});

  @override
  State<CreateReviewScreen> createState() => _CreateReviewScreenState();
}

class _CreateReviewScreenState extends State<CreateReviewScreen> {
  final _scoreController = Get.put(ScoreController());
  final _formKey = GlobalKey<FormState>();
  final _commentController = TextEditingController();

  int _overallRating = 5;
  int _qualityRating = 5;
  int _communicationRating = 5;
  int _timelinessRating = 5;
  int _professionalismRating = 5;
  bool _wouldRecommend = true;
  final List<String> _photoPaths = [];
  bool _isSubmitting = false;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _pickImages() async {
    final picker = ImagePicker();
    final images = await picker.pickMultiImage(
      maxWidth: 1920,
      maxHeight: 1080,
      imageQuality: 85,
    );

    if (images.isNotEmpty) {
      setState(() {
        _photoPaths.addAll(images.map((img) => img.path));
      });
    }
  }

  Future<void> _submitReview() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      // Upload photos if any
      List<String>? photoUrls;
      if (_photoPaths.isNotEmpty) {
        photoUrls = await _scoreController.uploadReviewPhotos(_photoPaths);
        if (photoUrls == null) {
          Get.snackbar(
            'Erreur',
            'Impossible de télécharger les photos',
            backgroundColor: AppColors.warning,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
          );
          setState(() => _isSubmitting = false);
          return;
        }
      }

      // Create review
      final request = CreateReviewRequest(
        projectId: widget.project.id,
        rating: _overallRating,
        comment: _commentController.text.isNotEmpty ? _commentController.text : null,
        qualityRating: _qualityRating,
        communicationRating: _communicationRating,
        timelinessRating: _timelinessRating,
        professionalismRating: _professionalismRating,
        wouldRecommend: _wouldRecommend,
        photos: photoUrls,
      );

      final success = await _scoreController.submitReview(request);

      if (success) {
        Get.back(result: true);
        Get.snackbar(
          'Merci!',
          'Votre avis a été publié',
          backgroundColor: AppColors.success,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
      } else {
        Get.snackbar(
          'Erreur',
          _scoreController.errorMessage.value,
          backgroundColor: AppColors.error,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
      }
    } finally {
      setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Donner votre avis'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          children: [
            // Project Info
            Card(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.base),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.project.title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 16,
                          backgroundImage: widget.project.artisan?.avatar != null
                              ? NetworkImage(widget.project.artisan!.avatar!)
                              : null,
                          child: widget.project.artisan?.avatar == null
                              ? const Icon(Icons.person, size: 16)
                              : null,
                        ),
                        const SizedBox(width: Spacing.sm),
                        Text(
                          widget.project.artisan?.name ?? 'Artisan',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: Spacing.xl),

            // Overall Rating
            Text(
              'Note générale',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: Spacing.md),
            Center(
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(5, (index) {
                      return IconButton(
                        icon: Icon(
                          index < _overallRating ? Icons.star : Icons.star_border,
                          color: AppColors.warning,
                          size: 48,
                        ),
                        onPressed: () {
                          setState(() => _overallRating = index + 1);
                        },
                      );
                    }),
                  ),
                  Text(
                    _getRatingLabel(_overallRating),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.warning,
                        ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Spacing.xl),

            // Detailed Ratings
            Text(
              'Évaluation détaillée',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: Spacing.md),
            _buildDetailedRatingRow('Qualité du travail', _qualityRating, (value) {
              setState(() => _qualityRating = value);
            }),
            _buildDetailedRatingRow('Communication', _communicationRating, (value) {
              setState(() => _communicationRating = value);
            }),
            _buildDetailedRatingRow('Ponctualité', _timelinessRating, (value) {
              setState(() => _timelinessRating = value);
            }),
            _buildDetailedRatingRow('Professionnalisme', _professionalismRating, (value) {
              setState(() => _professionalismRating = value);
            }),
            const SizedBox(height: Spacing.xl),

            // Recommendation
            Container(
              padding: const EdgeInsets.all(Spacing.base),
              decoration: BoxDecoration(
                color: _wouldRecommend
                    ? AppColors.success.withValues(alpha: 0.1)
                    : AppColors.lightBackground,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
                border: Border.all(
                  color: _wouldRecommend
                      ? AppColors.success.withValues(alpha: 0.3)
                      : AppColors.lightTextTertiary,
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    _wouldRecommend ? Icons.thumb_up : Icons.thumb_down_outlined,
                    color: _wouldRecommend ? AppColors.success : AppColors.lightTextSecondary,
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: Text(
                      'Recommanderiez-vous cet artisan?',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                  ),
                  Switch(
                    value: _wouldRecommend,
                    onChanged: (value) {
                      setState(() => _wouldRecommend = value);
                    },
                    activeTrackColor: AppColors.success,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Spacing.xl),

            // Comment
            Text(
              'Votre commentaire',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: Spacing.md),
            TextFormField(
              controller: _commentController,
              maxLines: 5,
              maxLength: 500,
              decoration: InputDecoration(
                hintText: 'Partagez votre expérience avec cet artisan...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                ),
              ),
            ),
            const SizedBox(height: Spacing.xl),

            // Photos
            Text(
              'Photos (optionnel)',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: Spacing.md),
            if (_photoPaths.isNotEmpty) ...[
              SizedBox(
                height: 120,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: _photoPaths.length + 1,
                  itemBuilder: (context, index) {
                    if (index == _photoPaths.length) {
                      return _buildAddPhotoButton();
                    }

                    return Stack(
                      children: [
                        Container(
                          width: 120,
                          margin: const EdgeInsets.only(right: Spacing.md),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(Spacing.radiusMd),
                            child: Image.file(
                              File(_photoPaths[index]),
                              fit: BoxFit.cover,
                            ),
                          ),
                        ),
                        Positioned(
                          top: 4,
                          right: 16,
                          child: IconButton(
                            icon: const Icon(Icons.close),
                            style: IconButton.styleFrom(
                              backgroundColor: Colors.black54,
                              foregroundColor: Colors.white,
                            ),
                            onPressed: () {
                              setState(() => _photoPaths.removeAt(index));
                            },
                          ),
                        ),
                      ],
                    );
                  },
                ),
              ),
            ] else
              _buildAddPhotoButton(),
            const SizedBox(height: Spacing.xxxl),

            // Submit Button
            ElevatedButton(
              onPressed: _isSubmitting ? null : _submitReview,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: Spacing.md),
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Publier l\'avis'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailedRatingRow(
    String label,
    int rating,
    Function(int) onChanged,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
          const SizedBox(height: Spacing.sm),
          Row(
            children: List.generate(5, (index) {
              return IconButton(
                icon: Icon(
                  index < rating ? Icons.star : Icons.star_border,
                  color: AppColors.warning,
                ),
                onPressed: () => onChanged(index + 1),
              );
            }),
          ),
        ],
      ),
    );
  }

  Widget _buildAddPhotoButton() {
    return InkWell(
      onTap: _pickImages,
      child: Container(
        width: 120,
        height: 120,
        decoration: BoxDecoration(
          color: AppColors.lightBackground,
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          border: Border.all(
            color: AppColors.lightTextTertiary,
            style: BorderStyle.solid,
          ),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.add_photo_alternate,
              size: 32,
              color: AppColors.lightTextSecondary,
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              'Ajouter',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.lightTextSecondary,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  String _getRatingLabel(int rating) {
    switch (rating) {
      case 5:
        return 'Excellent';
      case 4:
        return 'Très bon';
      case 3:
        return 'Bon';
      case 2:
        return 'Moyen';
      case 1:
        return 'Mauvais';
      default:
        return '';
    }
  }
}
