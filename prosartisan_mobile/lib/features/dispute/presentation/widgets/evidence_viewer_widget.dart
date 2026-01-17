import 'package:flutter/material.dart';

/// Widget for viewing evidence files and URLs
class EvidenceViewerWidget extends StatelessWidget {
  final List<String> evidenceUrls;

  const EvidenceViewerWidget({Key? key, required this.evidenceUrls})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (evidenceUrls.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.grey[50],
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.grey[300]!),
        ),
        child: Row(
          children: [
            Icon(Icons.info_outline, color: Colors.grey[600]),
            const SizedBox(width: 8),
            Text(
              'Aucune preuve fournie',
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      );
    }

    return Column(
      children: evidenceUrls.asMap().entries.map((entry) {
        final index = entry.key;
        final url = entry.value;
        return _buildEvidenceItem(context, url, index);
      }).toList(),
    );
  }

  Widget _buildEvidenceItem(BuildContext context, String url, int index) {
    final isImage = _isImageUrl(url);
    final displayName = _getUrlDisplayName(url);

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: isImage
              ? Colors.green.withOpacity(0.1)
              : Colors.blue.withOpacity(0.1),
          child: Icon(
            isImage ? Icons.image : Icons.link,
            color: isImage ? Colors.green : Colors.blue,
          ),
        ),
        title: Text(
          displayName,
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        subtitle: Text(
          url,
          style: TextStyle(color: Colors.grey[600], fontSize: 12),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (isImage)
              IconButton(
                icon: const Icon(Icons.visibility),
                onPressed: () => _showImagePreview(context, url),
                tooltip: 'Voir l\'image',
              ),
            IconButton(
              icon: const Icon(Icons.open_in_new),
              onPressed: () => _openUrl(url),
              tooltip: 'Ouvrir le lien',
            ),
          ],
        ),
      ),
    );
  }

  void _showImagePreview(BuildContext context, String imageUrl) {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppBar(
              title: const Text('Preuve image'),
              automaticallyImplyLeading: false,
              actions: [
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            Flexible(
              child: InteractiveViewer(
                child: Image.network(
                  imageUrl,
                  fit: BoxFit.contain,
                  loadingBuilder: (context, child, loadingProgress) {
                    if (loadingProgress == null) return child;
                    return Container(
                      height: 200,
                      child: Center(
                        child: CircularProgressIndicator(
                          value: loadingProgress.expectedTotalBytes != null
                              ? loadingProgress.cumulativeBytesLoaded /
                                    loadingProgress.expectedTotalBytes!
                              : null,
                        ),
                      ),
                    );
                  },
                  errorBuilder: (context, error, stackTrace) {
                    return Container(
                      height: 200,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.error_outline,
                            size: 48,
                            color: Colors.grey[400],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Impossible de charger l\'image',
                            style: TextStyle(color: Colors.grey[600]),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  TextButton.icon(
                    onPressed: () => _openUrl(imageUrl),
                    icon: const Icon(Icons.open_in_new),
                    label: const Text('Ouvrir dans le navigateur'),
                  ),
                ],
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
    // For now, we'll show a snackbar
    // In a real app, you'd use url_launcher package
  }

  bool _isImageUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;

    final path = uri.path.toLowerCase();
    return path.endsWith('.jpg') ||
        path.endsWith('.jpeg') ||
        path.endsWith('.png') ||
        path.endsWith('.gif') ||
        path.endsWith('.bmp') ||
        path.endsWith('.webp');
  }

  String _getUrlDisplayName(String url) {
    try {
      final uri = Uri.parse(url);
      final path = uri.path;
      if (path.isNotEmpty && path != '/') {
        final fileName = path.split('/').last;
        if (fileName.isNotEmpty) {
          return fileName;
        }
      }
      return uri.host.isNotEmpty ? uri.host : 'Lien';
    } catch (e) {
      return 'Lien invalide';
    }
  }
}
