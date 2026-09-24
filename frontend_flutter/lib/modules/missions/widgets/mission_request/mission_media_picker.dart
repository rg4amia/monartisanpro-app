import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import 'mission_request_colors.dart';

// ─── Media Picker ─────────────────────────────────────────────────────────────
class MissionMediaPicker extends StatelessWidget {
  final RxList<XFile> photos;
  final Rx<XFile?> video;
  final VoidCallback onPickImage;
  final VoidCallback onPickVideo;

  const MissionMediaPicker({
    super.key,
    required this.photos,
    required this.video,
    required this.onPickImage,
    required this.onPickVideo,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: MissionMediaButton(
                icon: Icons.add_photo_alternate_outlined,
                label: 'Ajouter une Photo',
                onTap: onPickImage,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: MissionMediaButton(
                icon: Icons.videocam_outlined,
                label: 'Ajouter une Vidéo',
                onTap: onPickVideo,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        const Text(
          'Max 5 photos et 1 vidéo (max 30s)',
          style: TextStyle(fontSize: 12, color: MissionRequestColors.muted),
        ),
        Obx(() {
          if (photos.isEmpty && video.value == null) {
            return const SizedBox.shrink();
          }
          return Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ...photos.map(
                  (photo) => MissionMediaThumbnail(
                    file: photo,
                    onRemove: () => photos.remove(photo),
                  ),
                ),
                if (video.value != null)
                  MissionMediaThumbnail(
                    file: video.value!,
                    isVideo: true,
                    onRemove: () => video.value = null,
                  ),
              ],
            ),
          );
        }),
      ],
    );
  }
}

class MissionMediaButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const MissionMediaButton({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 100,
        decoration: BoxDecoration(
          color: MissionRequestColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: MissionRequestColors.subtle,
            style: BorderStyle.solid,
          ),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 32, color: MissionRequestColors.muted),
            const SizedBox(height: 8),
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                color: MissionRequestColors.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class MissionMediaThumbnail extends StatelessWidget {
  final XFile file;
  final bool isVideo;
  final VoidCallback onRemove;

  const MissionMediaThumbnail({
    super.key,
    required this.file,
    this.isVideo = false,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            color: MissionRequestColors.subtle,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Center(
            child: Icon(
              isVideo ? Icons.videocam : Icons.image,
              color: MissionRequestColors.muted,
            ),
          ),
        ),
        Positioned(
          top: 4,
          right: 4,
          child: GestureDetector(
            onTap: onRemove,
            child: Container(
              width: 24,
              height: 24,
              decoration: const BoxDecoration(
                color: Colors.red,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.close, size: 16, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }
}
