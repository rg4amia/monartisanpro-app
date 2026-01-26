import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
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
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Signaler un litige',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(
        () => _controller.isSubmitting.value
            ? Center(
                child: CircularProgressIndicator(
                  color: AppColors.accentPrimary,
                ),
              )
            : _buildForm(),
      ),
    );
  }

  Widget _buildForm() {
    return SingleChildScrollView(
      padding: EdgeInsets.all(AppSpacing.base),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildMissionInfo(),
          SizedBox(height: AppSpacing.xl),
          _buildDisputeTypeSelector(),
          SizedBox(height: AppSpacing.xl),
          _buildDescriptionField(),
          SizedBox(height: AppSpacing.xl),
          _buildEvidenceSection(),
          SizedBox(height: AppSpacing.xxxl),
          _buildSubmitButton(),
        ],
      ),
    );
  }

  Widget _buildMissionInfo() {
    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Informations de la mission',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.sm),
          if (widget.missionTitle != null) ...[
            Text(
              'Mission: ${widget.missionTitle}',
              style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            ),
            SizedBox(height: AppSpacing.xs),
          ],
          if (widget.defendantName != null) ...[
            Text(
              'Contre: ${widget.defendantName}',
              style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildDisputeTypeSelector() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Type de litige *',
          style: AppTypography.sectionTitle.copyWith(
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        SizedBox(height: AppSpacing.sm),
        Obx(
          () => Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: DisputeType.allTypes.map((type) {
              final isSelected = _controller.selectedType.value == type;
              return FilterChip(
                label: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_controller.getTypeIcon(type)),
                    SizedBox(width: AppSpacing.xs),
                    Text(type.label),
                  ],
                ),
                selected: isSelected,
                onSelected: (selected) {
                  if (selected) {
                    _controller.setDisputeType(type);
                  }
                },
                backgroundColor: AppColors.cardBg,
                selectedColor: AppColors.accentPrimary.withValues(alpha: 0.2),
                checkmarkColor: AppColors.accentPrimary,
                labelStyle: AppTypography.bodySmall.copyWith(
                  color: isSelected
                      ? AppColors.accentPrimary
                      : AppColors.textSecondary,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: AppRadius.buttonRadius,
                ),
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
          style: AppTypography.sectionTitle.copyWith(
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        SizedBox(height: AppSpacing.sm),
        TextFormField(
          controller: _descriptionController,
          maxLines: 5,
          maxLength: 2000,
          style: AppTypography.body.copyWith(color: AppColors.textPrimary),
          decoration: InputDecoration(
            hintText: 'Décrivez en détail le problème rencontré...',
            hintStyle: AppTypography.body.copyWith(color: AppColors.textMuted),
            filled: true,
            fillColor: AppColors.cardBg,
            border: OutlineInputBorder(
              borderRadius: AppRadius.inputRadius,
              borderSide: BorderSide(color: AppColors.overlayMedium),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: AppRadius.inputRadius,
              borderSide: BorderSide(color: AppColors.overlayMedium),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppRadius.inputRadius,
              borderSide: BorderSide(color: AppColors.accentPrimary, width: 2),
            ),
            alignLabelWithHint: true,
            contentPadding: AppSpacing.inputPaddingDefault,
          ),
          onChanged: (value) => _controller.setDescription(value),
        ),
        SizedBox(height: AppSpacing.xs),
        Text(
          'Minimum 10 caractères requis',
          style: AppTypography.caption.copyWith(color: AppColors.textSecondary),
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
              style: AppTypography.sectionTitle.copyWith(
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
            ),
            const Spacer(),
            TextButton.icon(
              onPressed: _showEvidenceOptions,
              icon: Icon(Icons.add, color: AppColors.accentPrimary),
              label: Text(
                'Ajouter',
                style: AppTypography.button.copyWith(
                  color: AppColors.accentPrimary,
                ),
              ),
            ),
          ],
        ),
        SizedBox(height: AppSpacing.sm),
        Text(
          'Ajoutez des photos, documents ou liens pour appuyer votre litige',
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textSecondary,
          ),
        ),
        SizedBox(height: AppSpacing.base),
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
          backgroundColor: AppColors.accentPrimary,
          foregroundColor: Colors.white,
          padding: EdgeInsets.symmetric(vertical: AppSpacing.base),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.buttonRadius),
        ),
        child: Text(
          'Signaler le litige',
          style: AppTypography.button.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  void _showEvidenceOptions() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.cardBg,
      shape: RoundedRectangleBorder(borderRadius: AppRadius.modalRadius),
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: Icon(Icons.camera_alt, color: AppColors.textPrimary),
              title: Text(
                'Prendre une photo',
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: Icon(Icons.photo_library, color: AppColors.textPrimary),
              title: Text(
                'Choisir depuis la galerie',
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
            ListTile(
              leading: Icon(Icons.link, color: AppColors.textPrimary),
              title: Text(
                'Ajouter un lien',
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
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
        backgroundColor: AppColors.cardBg,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.largeRadius),
        title: Text(
          'Ajouter un lien',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        content: TextField(
          controller: urlController,
          style: AppTypography.body.copyWith(color: AppColors.textPrimary),
          decoration: InputDecoration(
            hintText: 'https://exemple.com/document.pdf',
            labelText: 'URL du document',
            hintStyle: AppTypography.body.copyWith(color: AppColors.textMuted),
            labelStyle: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
            ),
            filled: true,
            fillColor: AppColors.elevatedBg,
            border: OutlineInputBorder(
              borderRadius: AppRadius.inputRadius,
              borderSide: BorderSide(color: AppColors.overlayMedium),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: AppRadius.inputRadius,
              borderSide: BorderSide(color: AppColors.overlayMedium),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppRadius.inputRadius,
              borderSide: BorderSide(color: AppColors.accentPrimary, width: 2),
            ),
          ),
          keyboardType: TextInputType.url,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(
              'Annuler',
              style: AppTypography.button.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
          TextButton(
            onPressed: () {
              final url = urlController.text.trim();
              if (url.isNotEmpty) {
                _controller.addEvidenceUrl(url);
                Navigator.pop(context);
              }
            },
            child: Text(
              'Ajouter',
              style: AppTypography.button.copyWith(
                color: AppColors.accentPrimary,
              ),
            ),
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
