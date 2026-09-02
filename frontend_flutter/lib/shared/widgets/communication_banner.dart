import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/communication_model.dart';

class CommunicationBanner extends StatelessWidget {
  final List<CommunicationModel> announcements;

  const CommunicationBanner({
    super.key,
    required this.announcements,
  });

  @override
  Widget build(BuildContext context) {
    if (announcements.isEmpty) return const SizedBox.shrink();

    return Column(
      children: announcements.map((announcement) {
        return Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [
                Color(0xFFFFFAF0), // Creamy warmth
                Color(0xFFFFF0DB), // Light Amber
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: const Color(0xFFF5D6A5),
              width: 1.5,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.amber.withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFD67C00).withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.campaign,
                  color: Color(0xFFD67C00),
                  size: 24,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      announcement.titre,
                      style: const TextStyle(
                        fontFamily: 'Outfit',
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                        color: Color(0xFF5C3300),
                      ),
                    ),
                    const SizedBox(height: 6),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 100),
                      child: SingleChildScrollView(
                        physics: const BouncingScrollPhysics(),
                        child: Text(
                          announcement.contenu,
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 14,
                            color: Color(0xFF7A4A0B),
                            height: 1.4,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}

class LeSaviezVousCarousel extends StatefulWidget {
  final List<CommunicationModel> tips;

  const LeSaviezVousCarousel({
    super.key,
    required this.tips,
  });

  @override
  State<LeSaviezVousCarousel> createState() => _LeSaviezVousCarouselState();
}

class _LeSaviezVousCarouselState extends State<LeSaviezVousCarousel> {
  late final PageController _pageController;
  int _currentPage = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    if (widget.tips.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 8), (timer) {
        if (_pageController.hasClients) {
          final nextPage = (_currentPage + 1) % widget.tips.length;
          _pageController.animateToPage(
            nextPage,
            duration: const Duration(milliseconds: 600),
            curve: Curves.easeInOutCubic,
          );
        }
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.tips.isEmpty) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(top: 4, bottom: 12),
      height: 140,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [
            Color(0xFFEDF5FF), // Soft premium blue
            Color(0xFFD6E4FF),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(
          color: const Color(0xFFADC6FF),
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.blue.withValues(alpha: 0.06),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: Stack(
          children: [
            PageView.builder(
              controller: _pageController,
              onPageChanged: (page) {
                setState(() {
                  _currentPage = page;
                });
              },
              itemCount: widget.tips.length,
              itemBuilder: (context, index) {
                final tip = widget.tips[index];
                return Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0F5132).withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: const Icon(
                          Icons.lightbulb_outline,
                          color: Color(0xFF0B5ED7),
                          size: 26,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'LE SAVIEZ-VOUS ?',
                              style: TextStyle(
                                fontFamily: 'Outfit',
                                fontWeight: FontWeight.w800,
                                fontSize: 11,
                                letterSpacing: 1.5,
                                color: Color(0xFF0A58CA),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              tip.titre,
                              style: const TextStyle(
                                fontFamily: 'Outfit',
                                fontWeight: FontWeight.bold,
                                fontSize: 15,
                                color: Color(0xFF052C65),
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Expanded(
                              child: SingleChildScrollView(
                                physics: const BouncingScrollPhysics(),
                                child: Text(
                                  tip.contenu,
                                  style: const TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 13,
                                    color: Color(0xFF0A58CA),
                                    height: 1.3,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
            if (widget.tips.length > 1)
              Positioned(
                bottom: 12,
                left: 0,
                right: 0,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    widget.tips.length,
                    (index) => AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      height: 5,
                      width: _currentPage == index ? 16 : 5,
                      decoration: BoxDecoration(
                        color: _currentPage == index
                            ? const Color(0xFF0B5ED7)
                            : const Color(0xFF0B5ED7).withValues(alpha: 0.3),
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
