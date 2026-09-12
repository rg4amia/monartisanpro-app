import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Visionneuse plein écran zoomable, partagée par le catalogue, la discussion
/// de chantier et les preuves de jalon. Accepte plusieurs images pour pouvoir
/// les faire défiler sans revenir en arrière à chaque fois.
void openImageViewer(
  BuildContext context, {
  required List<String> urls,
  int initialIndex = 0,
  String? title,
}) {
  final images = urls.where((u) => u.trim().isNotEmpty).toList();
  if (images.isEmpty) return;

  final startAt = initialIndex.clamp(0, images.length - 1);

  unawaited(
    Get.dialog(
      _ImageViewerDialog(urls: images, initialIndex: startAt, title: title),
    ),
  );
}

class _ImageViewerDialog extends StatefulWidget {
  const _ImageViewerDialog({
    required this.urls,
    required this.initialIndex,
    this.title,
  });

  final List<String> urls;
  final int initialIndex;
  final String? title;

  @override
  State<_ImageViewerDialog> createState() => _ImageViewerDialogState();
}

class _ImageViewerDialogState extends State<_ImageViewerDialog> {
  late final PageController _pageController;
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _pageController = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hasMultiple = widget.urls.length > 1;

    return Dialog.fullscreen(
      backgroundColor: Colors.black,
      child: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            physics: hasMultiple
                ? const PageScrollPhysics()
                : const NeverScrollableScrollPhysics(),
            itemCount: widget.urls.length,
            onPageChanged: (i) => setState(() => _index = i),
            itemBuilder: (_, i) => InteractiveViewer(
              minScale: 0.5,
              maxScale: 4.0,
              child: Center(
                child: CachedNetworkImage(
                  imageUrl: widget.urls[i],
                  fit: BoxFit.contain,
                  placeholder: (context, url) => const CircularProgressIndicator(
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                  errorWidget: (context, url, error) => const Icon(
                    Icons.broken_image_outlined,
                    color: Colors.white70,
                    size: 48,
                  ),
                ),
              ),
            ),
          ),

          if (widget.title != null)
            Positioned(
              top: 20,
              left: 20,
              child: SafeArea(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.black45,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    widget.title!,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                  ),
                ),
              ),
            ),

          Positioned(
            top: 20,
            right: 20,
            child: SafeArea(
              child: IconButton(
                icon: const CircleAvatar(
                  backgroundColor: Colors.black45,
                  child: Icon(Icons.close, color: Colors.white),
                ),
                onPressed: () => Get.back(),
              ),
            ),
          ),

          if (hasMultiple)
            Positioned(
              bottom: 28,
              left: 0,
              right: 0,
              child: SafeArea(
                child: Center(
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '${_index + 1} / ${widget.urls.length}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
