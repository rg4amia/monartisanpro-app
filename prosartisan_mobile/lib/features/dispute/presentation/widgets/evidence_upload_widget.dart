import 'dart:io';
import 'package:flutter/material.dart';

/// Widget for displaying and managing evidence files and URLs
class EvidenceUploadWidget extends StatelessWidget {
  final List<File> evidenceFiles;
  final List<String> evidenceUrls;
  final Function(int) onRemoveFile;
  final Function(int) onRemoveUrl;

  const EvidenceUploadWidget({
    Key? key,
    required this.evidenceFiles,
    required this.evidenceUrls,
    required this.onRemoveFile,
    required this.onRemoveUrl,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (evidenceFiles.isEmpty && evidenceUrls.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey[300]!),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          children: [
            Icon(
              Icons.cloud_upload_outlined,
              size: 48,
              color: Colors.grey[400],
            ),
            const SizedBox(height: 8),
            Text(
              'Aucune preuve ajoutée',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Colors.grey[600],
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

  Widget _buildFileItem(BuildContext context, File file, int index, bool isFile) {
    final fileName = file.path.split('/').last;
    final isImage = _isImageFile(fileName);

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Theme.of(context).primaryColor.withOpacity(0.1),
          child: Icon(
            isImage ? Icons.image : Icons.insert_drive_file,
            color: Theme.of(context).primaryColor,
          ),
        ),
        title: Text(
          fileName,
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        subtitle: Text(_getFileSize(file)),
        trailing: IconButton(
          icon: const Icon(Icons.delete_outline, color: Colors.red),
          onPressed: () => onRemoveFile(index),
        ),
        onTap: isImage ? () => _showImagePreview(context, file) : null,
      ),
    );
  }

  Widget _buildUrlItem(BuildContext context, String url, int index) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Colors.blue.withOpacity(0.1),
          child: const Icon(
            Icons.link,
            color: Colors.blue,
          ),
        ),
        title: Text(
          _getUrlDisplayName(url),
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        subtitle: Text(
          url,
          style: TextStyle(
            color: Colors.grey[600],
            fontSize: 12,
          ),
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
            Flexible(
              child: Image.file(
                file,
                fit: BoxFit.contain,
              ),
            ),
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