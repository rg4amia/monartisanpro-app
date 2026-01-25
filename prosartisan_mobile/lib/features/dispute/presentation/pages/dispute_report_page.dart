import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/buttons/secondary_button.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../../domain/models/dispute.dart';
import '../controllers/dispute_controller.dart';
import '../widgets/evidence_upload_widget.dart';

/// Page for reporting a new dispute
///
/// Requirement 9.1: Create dispute reporting form with evidence upload
class DisputeReportPage extends StatefulWidget {
  final String missionId;
  final String defendantId;
  final String? missionTitle;
  final String? defendantName;

  const DisputeReportPage({
    super.key,
    required this.missionId,
    required this.defendantId,
    this.missionTitle,
    this.defendantName,
  });

  @override
  State<DisputeReportPage> createState() => _DisputeReportPageState();
}

class _DisputeReportPageState extends State<DisputeReportPage> {
  final DisputeController _controller = Get.find<DisputeController>();
  final TextEditingController _descriptionController = TextEditingController();
  final ImagePicker _imagePicker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _controller.setDisputeData(
      missionId: widget.missionId,
      defendantId: widget.defendantId,
    );
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Signaler un litige',
          style: AppTypography.headingMedium.copyWith(
            color: AppColors.textLight,
          ),
        ),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.textLight,
        elevation: 0,
      ),
      body: Obx(
        () => _controller.isSubmitting.value
            ? Center(child: CircularProgressIndicator(color: AppColors.primary))
            : _buildForm(),
      ),
    );
  }

  Widget _buildForm() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildMissionInfo(),
          const SizedBox(height: 24),
          _buildDisputeTypeSelector(),
          const SizedBox(height: 24),
          _buildDescriptionField(),
          const SizedBox(height: 24),
          _buildEvidenceSection(),
          const SizedBox(height: 32),
          _buildSubmitButton(),
        ],
      ),
    );
  }

  Widget _buildMissionInfo() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Informations de la mission',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            if (widget.missionTitle != null) ...[
              Text(
                'Mission: ${widget.missionTitle}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 4),
            ],
            if (widget.defendantName != null) ...[
              Text(
                'Contre: ${widget.defendantName}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildDisputeTypeSelector() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Type de litige *',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        Obx(
          () => Wrap(
            spacing: 8.0,
            runSpacing: 8.0,
            children: DisputeType.allTypes.map((type) {
              final isSelected = _controller.selectedType.value == type;
              return FilterChip(
                label: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_controller.getTypeIcon(type)),
                    const SizedBox(width: 4),
                    Text(type.label),
                  ],
                ),
                selected: isSelected,
                onSelected: (selected) {
                  if (selected) {
                    _controller.setDisputeType(type);
                  }
                },
                selectedColor: AppColors.primary.withValues(alpha: 0.2),
                checkmarkColor: AppColors.primary,
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  Widget _buildDescriptionField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Description du litige *',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: _descriptionController,
          maxLines: 5,
          maxLength: 2000,
          decoration: const InputDecoration(
            hintText: 'Décrivez en détail le problème rencontré...',
            border: OutlineInputBorder(),
            alignLabelWithHint: true,
          ),
          onChanged: (value) => _controller.setDescription(value),
        ),
        const SizedBox(height: 4),
        Text(
          'Minimum 10 caractères requis',
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
        ),
      ],
    );
  }

  Widget _buildEvidenceSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              'Preuves (optionnel)',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const Spacer(),
            TextButton.icon(
              onPressed: _showEvidenceOptions,
              icon: const Icon(Icons.add),
              label: const Text('Ajouter'),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          'Ajoutez des photos, documents ou liens pour appuyer votre litige',
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
        ),
        const SizedBox(height: 16),
        Obx(
          () => EvidenceUploadWidget(
            evidenceFiles: _controller.evidenceFiles,
            evidenceUrls: _controller.evidenceUrls,
            onRemoveFile: _controller.removeEvidenceFile,
            onRemoveUrl: _controller.removeEvidenceUrl,
          ),
        ),
      ],
    );
  }

  Widget _buildSubmitButton() {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: _submitDispute,
        style: ElevatedButton.styleFrom(
          backgroundColor: Theme.of(context).primaryColor,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
        ),
        child: const Text(
          'Signaler le litige',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }

  void _showEvidenceOptions() {
    showModalBottomSheet(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Prendre une photo'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Choisir depuis la galerie'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
            ListTile(
              leading: const Icon(Icons.link),
              title: const Text('Ajouter un lien'),
              onTap: () {
                Navigator.pop(context);
                _showAddUrlDialog();
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: source,
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
      );

      if (image != null) {
        _controller.addEvidenceFile(File(image.path));
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de sélectionner l\'image: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    }
  }

  void _showAddUrlDialog() {
    final TextEditingController urlController = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Ajouter un lien'),
        content: TextField(
          controller: urlController,
          decoration: const InputDecoration(
            hintText: 'https://exemple.com/document.pdf',
            labelText: 'URL du document',
          ),
          keyboardType: TextInputType.url,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () {
              final url = urlController.text.trim();
              if (url.isNotEmpty) {
                _controller.addEvidenceUrl(url);
                Navigator.pop(context);
              }
            },
            child: const Text('Ajouter'),
          ),
        ],
      ),
    );
  }

  Future<void> _submitDispute() async {
    final success = await _controller.reportDispute();
    if (success) {
      Navigator.pop(context, true);
    }
  }
}
