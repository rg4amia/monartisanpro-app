import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
}

class MissionRequestScreen extends StatefulWidget {
  const MissionRequestScreen({super.key});

  @override
  State<MissionRequestScreen> createState() => _MissionRequestScreenState();
}

class _MissionRequestScreenState extends State<MissionRequestScreen> {
  final _descCtrl = TextEditingController();
  final _selectedCategory = ''.obs;
  final _selectedCategoryId = 0.obs;
  final _selectedTradeId = 0.obs;
  final _location = 'Abidjan, Côte d\'Ivoire'.obs;
  final _locationDetail = 'Cocody, Riviera 3'.obs;
  final _latitude = 0.0.obs;
  final _longitude = 0.0.obs;
  final _photos = <XFile>[].obs;
  final _video = Rx<XFile?>(null);

  static const _categories = [
    {'label': 'Plumbing', 'icon': Icons.plumbing_outlined},
    {'label': 'Electricity', 'icon': Icons.electric_bolt_outlined},
    {'label': 'Painting', 'icon': Icons.format_paint_outlined},
    {'label': 'Masonry', 'icon': Icons.foundation_outlined},
  ];

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>?;
    if (args != null && args['category'] != null) {
      _selectedCategory.value = args['category'] as String;
    }
  }

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    if (_photos.length >= 5) {
      Get.snackbar('Limit Reached', 'Maximum 5 photos allowed');
      return;
    }
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.gallery);
    if (image != null) {
      _photos.add(image);
    }
  }

  Future<void> _pickVideo() async {
    final picker = ImagePicker();
    final video = await picker.pickVideo(source: ImageSource.gallery);
    if (video != null) {
      _video.value = video;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _SectionTitle(title: 'Service Category'),
                    const SizedBox(height: 12),
                    Obx(() => _selectedCategory.value.isEmpty
                        ? _SelectServiceButton(
                            onTap: () async {
                              final result = await Get.toNamed(Routes.services);
                              if (result != null && result is Map) {
                                _selectedCategory.value =
                                    result['trade']?.name ?? '';
                                _selectedCategoryId.value =
                                    result['sector']?.id ?? 0;
                                _selectedTradeId.value =
                                    result['trade']?.id ?? 0;
                              }
                            },
                          )
                        : _SelectedServiceCard(
                            category: _selectedCategory.value,
                            onChangeTap: () async {
                              final result = await Get.toNamed(Routes.services);
                              if (result != null && result is Map) {
                                _selectedCategory.value =
                                    result['trade']?.name ?? '';
                                _selectedCategoryId.value =
                                    result['sector']?.id ?? 0;
                                _selectedTradeId.value =
                                    result['trade']?.id ?? 0;
                              }
                            },
                          )),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Mission Details'),
                    const SizedBox(height: 8),
                    const Text(
                      'Describe your problem',
                      style: TextStyle(fontSize: 13, color: _C.muted),
                    ),
                    const SizedBox(height: 12),
                    _DescriptionField(controller: _descCtrl),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Visuals (Photos or Videos)'),
                    const SizedBox(height: 12),
                    _MediaPicker(
                      photos: _photos,
                      video: _video,
                      onPickImage: _pickImage,
                      onPickVideo: _pickVideo,
                    ),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Your Location'),
                    const SizedBox(height: 12),
                    Obx(() => _LocationCard(
                          location: _location.value,
                          detail: _locationDetail.value,
                          onChangeTap: () async {
                            final result =
                                await Get.toNamed(Routes.locationPicker);
                            if (result != null && result is Map) {
                              _latitude.value = result['latitude'] ?? 0.0;
                              _longitude.value = result['longitude'] ?? 0.0;
                              _location.value =
                                  result['address'] ?? 'Location selected';
                              _locationDetail.value =
                                  'Lat: ${_latitude.value.toStringAsFixed(4)}, Lng: ${_longitude.value.toStringAsFixed(4)}';
                            }
                          },
                        )),
                    const SizedBox(height: 32),
                    _SearchButton(
                      onPressed: () {
                        if (_selectedCategory.value.isEmpty) {
                          Get.snackbar('Error', 'Please select a category');
                          return;
                        }
                        if (_descCtrl.text.length < 10) {
                          Get.snackbar('Error', 'Please provide more details');
                          return;
                        }
                        // Navigate to artisan search/list
                        Get.toNamed(Routes.artisanMap);
                      },
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Create Mission',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
          ),
          const SizedBox(width: 40), // Balance the back button
        ],
      ),
    );
  }
}

// ─── Section Title ────────────────────────────────────────────────────────────
class _SectionTitle extends StatelessWidget {
  final String title;
  const _SectionTitle({required this.title});

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: _C.ink,
      ),
    );
  }
}

// ─── Category Chips ───────────────────────────────────────────────────────────
class _SelectServiceButton extends StatelessWidget {
  final VoidCallback onTap;

  const _SelectServiceButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _C.subtle, width: 2),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.add_circle_outline, color: _C.primary, size: 24),
            SizedBox(width: 12),
            Text(
              'Select Service Category',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: _C.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SelectedServiceCard extends StatelessWidget {
  final String category;
  final VoidCallback onChangeTap;

  const _SelectedServiceCard({
    required this.category,
    required this.onChangeTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.primaryLight,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.primary),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _C.primary,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.check, color: Colors.white, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Selected Service',
                  style: TextStyle(
                    fontSize: 12,
                    color: _C.muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  category,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: onChangeTap,
            child: const Text(
              'Change',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: _C.primary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryChips extends StatelessWidget {
  final List<Map<String, dynamic>> categories;
  final RxString selected;

  const _CategoryChips({
    required this.categories,
    required this.selected,
  });

  @override
  Widget build(BuildContext context) {
    return Obx(() => Wrap(
          spacing: 12,
          runSpacing: 12,
          children: categories.map((cat) {
            final label = cat['label'] as String;
            final icon = cat['icon'] as IconData;
            final isSelected = selected.value == label;

            return GestureDetector(
              onTap: () => selected.value = label,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: isSelected ? _C.primary : _C.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? _C.primary : _C.subtle,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      icon,
                      size: 18,
                      color: isSelected ? Colors.white : _C.ink,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      label,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: isSelected ? Colors.white : _C.ink,
                      ),
                    ),
                  ],
                ),
              ),
            );
          }).toList(),
        ));
  }
}

// ─── Description Field ────────────────────────────────────────────────────────
class _DescriptionField extends StatelessWidget {
  final TextEditingController controller;
  const _DescriptionField({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: TextField(
        controller: controller,
        maxLines: 6,
        maxLength: 500,
        decoration: const InputDecoration(
          hintText:
              'Please provide as much detail as possible about the issue...',
          hintStyle: TextStyle(color: _C.muted, fontSize: 14),
          border: InputBorder.none,
          contentPadding: EdgeInsets.all(16),
          counterStyle: TextStyle(fontSize: 12, color: _C.muted),
        ),
      ),
    );
  }
}

// ─── Media Picker ─────────────────────────────────────────────────────────────
class _MediaPicker extends StatelessWidget {
  final RxList<XFile> photos;
  final Rx<XFile?> video;
  final VoidCallback onPickImage;
  final VoidCallback onPickVideo;

  const _MediaPicker({
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
              child: _MediaButton(
                icon: Icons.add_photo_alternate_outlined,
                label: 'Add Photo',
                onTap: onPickImage,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _MediaButton(
                icon: Icons.videocam_outlined,
                label: 'Add Video',
                onTap: onPickVideo,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        const Text(
          'Max 5 photos and 1 video (max 30s)',
          style: TextStyle(fontSize: 12, color: _C.muted),
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
                ...photos.map((photo) => _MediaThumbnail(
                      file: photo,
                      onRemove: () => photos.remove(photo),
                    )),
                if (video.value != null)
                  _MediaThumbnail(
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

class _MediaButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _MediaButton({
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
          color: _C.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _C.subtle, style: BorderStyle.solid),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 32, color: _C.muted),
            const SizedBox(height: 8),
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                color: _C.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MediaThumbnail extends StatelessWidget {
  final XFile file;
  final bool isVideo;
  final VoidCallback onRemove;

  const _MediaThumbnail({
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
            color: _C.subtle,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Center(
            child: Icon(
              isVideo ? Icons.videocam : Icons.image,
              color: _C.muted,
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

// ─── Location Card ────────────────────────────────────────────────────────────
class _LocationCard extends StatelessWidget {
  final String location;
  final String detail;
  final VoidCallback onChangeTap;

  const _LocationCard({
    required this.location,
    required this.detail,
    required this.onChangeTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.location_on, color: _C.primary, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  location,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  detail,
                  style: const TextStyle(
                    fontSize: 12,
                    color: _C.muted,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: onChangeTap,
            child: const Text(
              'Change',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: _C.primary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Search Button ────────────────────────────────────────────────────────────
class _SearchButton extends StatelessWidget {
  final VoidCallback onPressed;
  const _SearchButton({required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: _C.primary,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.search, size: 20),
            SizedBox(width: 8),
            Text(
              'Search for Artisans',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
