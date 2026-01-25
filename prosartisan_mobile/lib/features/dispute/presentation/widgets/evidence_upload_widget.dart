import 'dart:io';
import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

/// Widget for displaying and managing evidence files and URLs
class EvidenceUploadWidget extends StatelessWidget {
  final List<File> evidenceFiles;
  final List<String> evidenceUrls;
  final Function(int) onRemoveFile;
  final Function(int) onRemoveUrl;

  const EvidenceUploadWidget({
    super.key,
    required this.evidenceFiles,
    required this.evidenceUrls,
    required this.onRemoveFile,
    required this.onRemoveUrl,
  });

  @override
  Widget build(BuildContext context) {
    if (evidenceFiles.isEmpty && evidenceUrls.isEmpty) {
      return Container(
        padding: EdgeInsets.all(AppSpacing.xl),
        decoration: BoxDecoration(
          border: Border.all(color: AppColors.overlayMedium),
          borderRadius: AppRadius.cardRadius,
          color: AppColors.cardBg,
        ),
        child: Column(
          children: [
            Icon(
              Icons.cloud_upload_outlined,
              size: 48,
              color: AppColors.textMuted,
            ),
            SizedBox(height: AppSpacing.sm),
            Text(
              'Aucune preuve ajoutée',
              style: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        // Display uploaded files
        if (evidenceFiles.isNotEmpty) ...[
          ...evidenceFiles.asMap().entries.map((entry) {
            final index = entry.key;
            final file = entry.value;
            return _buildFileItem(context, file, index, true);
          }).toList(),
        ],

        // Display URLs
        if (evidenceUrls.isNotEmpty) ...[
          ...evidenceUrls.asMap().entries.map((entry) {
            final index = entry.key;
            final url = entry.value;
            return _buildUrlItem(context, url, index);
          }).toList(),
        ],
      ],
    );
  }

  Widget _buildFileItem(
    BuildContext context,
    File file,
    int index,
    bool isFile,
  ) {
    final fileName = file.path.split('/').last;
    final isImage = _isImageFile(fileName);

    return Container(
      margin: EdgeInsets.only(bottom: AppSpacing.sm),
      padding: EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: AppColors.accentPrimary.withValues(alpha: 0.1),
            child: Icon(
              isImage ? Icons.image : Icons.insert_drive_file,
              color: AppColors.accentPrimary,
            ),
          ),
          SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  fileName,
                  style: AppTypography.body.copyWith(
                    fontWeight: FontWeight.w500,
                    color: AppColors.textPrimary,
                  ),
                ),
                SizedBox(height: AppSpacing.xs),
                Text(
                  _getFileSize(file),
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: Icon(Icons.delete_outline, color: AppColors.accentDanger),
            onPressed: () => onRemoveFile(index),
          ),
        ],
      ),
    );
  }

  Widget _buildUrlItem(BuildContext context, String url, int index) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Colors.blue.withOpacity(0.1),
          child: const Icon(Icons.link, color: Colors.blue),
        ),
        title: Text(
          _getUrlDisplayName(url),
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        subtitle: Text(
          url,
          style: TextStyle(color: Colors.grey[600], fontSize: 12),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: IconButton(
          icon: const Icon(Icons.delete_outline, color: Colors.red),
          onPressed: () => onRemoveUrl(index),
        ),
        onTap: () => _openUrl(url),
      ),
    );
  }

  void _showImagePreview(BuildContext context, File file) {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppBar(
              title: Text(file.path.split('/').last),
              automaticallyImplyLeading: false,
              actions: [
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            Flexible(child: Image.file(file, fit: BoxFit.contain)),
          ],
        ),
      ),
    );
  }

  void _openUrl(String url) {
    // This would typically open the URL in a browser
    // Implementation depends on your URL launcher setup
  }

  bool _isImageFile(String fileName) {
    final extension = fileName.toLowerCase().split('.').last;
    return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].contains(extension);
  }

  String _getFileSize(File file) {
    final bytes = file.lengthSync();
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  String _getUrlDisplayName(String url) {
    try {
      final uri = Uri.parse(url);
      final path = uri.path;
      if (path.isNotEmpty && path != '/') {
        return path.split('/').last;
      }
      return uri.host;
    } catch (e) {
      return 'Lien';
    }
  }
}
