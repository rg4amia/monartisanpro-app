import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  final List<OnboardingPage> _pages = [
    OnboardingPage(
      image: 'assets/images/onboarding_1.png',
      title: 'Des Experts Qualifiés à votre Service',
      description:
          'Trouvez en quelques clics des artisans professionnels vérifiés en Côte d\'Ivoire pour tous vos travaux de plomberie, électricité, maçonnerie et bien plus.',
      backgroundColor: const Color(0xFFFFF7EA),
      accentColor: const Color(0xFFFDB750),
    ),
    OnboardingPage(
      image: 'assets/images/onboarding_2.png',
      title: 'Séquestre Sécurisé & Anti-Fraude',
      description:
          'Vos paiements sont bloqués de manière sécurisée et libérés étape par étape uniquement lorsque vous validez chaque jalon des travaux.',
      backgroundColor: const Color(0xFFEEF2F6),
      accentColor: const Color(0xFF5B5FEF),
    ),
    OnboardingPage(
      image: 'assets/images/onboarding_3.png',
      title: "La Transparence avec le Score ProsArtisan",
      description:
          'Évaluez vos artisans sur la fiabilité, la qualité, l\'intégrité et la réactivité. Choisissez l\'excellence grâce à un score transparent et audité.',
      backgroundColor: Colors.white,
      accentColor: const Color(0xFFFDB750),
      showProfile: true,
    ),
    OnboardingPage(
      image: 'assets/images/onboarding_4.png',
      title: 'Prêt à bâtir la Confiance ?',
      description:
          'Rejoignez notre réseau en Côte d\'Ivoire en tant que Client, Artisan ou Fournisseur et bénéficiez d\'un écosystème d\'échanges fiable et sécurisé.',
      backgroundColor: const Color(0xFFFFF7EA),
      accentColor: const Color(0xFF5B5FEF),
      showRoleSelection: true,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _pageController.addListener(() {
      setState(() {});
    });
  }

  void _onPageChanged(int page) {
    setState(() {
      _currentPage = page;
    });
  }

  void _nextPage() {
    if (_currentPage < _pages.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeOutCubic,
      );
    } else {
      _completeOnboarding();
    }
  }

  void _skipOnboarding() {
    _completeOnboarding();
  }

  void _completeOnboarding() {
    StorageService.setOnboarded(true);
    Get.offAllNamed(Routes.login);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    double scrollPosition = 0.0;
    if (_pageController.hasClients) {
      scrollPosition = _pageController.page ?? 0.0;
    } else {
      scrollPosition = _currentPage.toDouble();
    }

    // Dynamic background bubble animations
    double bubble1Left = -100 + (scrollPosition * 120);
    double bubble1Top = -50 + (scrollPosition * 40);
    Color bubble1Color = Color.lerp(
      const Color(0xFFFDB750).withValues(alpha: 0.18),
      const Color(0xFF5B5FEF).withValues(alpha: 0.12),
      scrollPosition / (_pages.length - 1),
    ) ?? const Color(0xFFFDB750).withValues(alpha: 0.15);

    double bubble2Right = -80 - (scrollPosition * 60);
    double bubble2Bottom = 80 + (scrollPosition * 90);
    Color bubble2Color = Color.lerp(
      const Color(0xFF5B5FEF).withValues(alpha: 0.12),
      const Color(0xFF4CAF50).withValues(alpha: 0.18),
      scrollPosition / (_pages.length - 1),
    ) ?? const Color(0xFF5B5FEF).withValues(alpha: 0.12);

    return Scaffold(
      backgroundColor: Colors.white,
      body: Stack(
        children: [
          // Background Glow Bubble 1
          Positioned(
            left: bubble1Left,
            top: bubble1Top,
            child: _GlowBubble(size: 280, color: bubble1Color),
          ),
          // Background Glow Bubble 2
          Positioned(
            right: bubble2Right,
            bottom: bubble2Bottom,
            child: _GlowBubble(size: 320, color: bubble2Color),
          ),
          
          SafeArea(
            child: Column(
              children: [
                // AppBar Elements
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _currentPage > 0
                          ? Container(
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.6),
                                shape: BoxShape.circle,
                                border: Border.all(color: Colors.black.withValues(alpha: 0.06)),
                              ),
                              child: IconButton(
                                icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Color(0xFF1A1A1A), size: 18),
                                onPressed: () {
                                  _pageController.previousPage(
                                    duration: const Duration(milliseconds: 500),
                                    curve: Curves.easeOutCubic,
                                  );
                                },
                              ),
                            )
                          : const SizedBox(width: 48),
                      // App logo text
                      const Text(
                        "ProsArtisan",
                        style: TextStyle(
                          color: Color(0xFF1A1A1A),
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.5,
                        ),
                      ),
                      _currentPage < _pages.length - 1
                          ? TextButton(
                              onPressed: _skipOnboarding,
                              child: const Text(
                                'Passer',
                                style: TextStyle(
                                  color: Color(0xFF7F7F7F),
                                  fontSize: 15,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            )
                          : const SizedBox(width: 48),
                    ],
                  ),
                ),
                
                // PageView
                Expanded(
                  child: PageView.builder(
                    controller: _pageController,
                    onPageChanged: _onPageChanged,
                    itemCount: _pages.length,
                    itemBuilder: (context, index) {
                      return _buildPage(_pages[index]);
                    },
                  ),
                ),

                // Bottom section
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      // Page indicator (Dots)
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(
                          _pages.length,
                          (index) {
                            final isSelected = _currentPage == index;
                            return AnimatedContainer(
                              duration: const Duration(milliseconds: 350),
                              curve: Curves.easeOutBack,
                              margin: const EdgeInsets.symmetric(horizontal: 4),
                              width: isSelected ? 28.0 : 8.0,
                              height: 8.0,
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? _pages[index].accentColor
                                    : const Color(0xFFE0E0E0),
                                borderRadius: BorderRadius.circular(4),
                                boxShadow: isSelected ? [
                                  BoxShadow(
                                    color: _pages[index].accentColor.withValues(alpha: 0.35),
                                    blurRadius: 8,
                                    offset: const Offset(0, 3),
                                  )
                                ] : null,
                              ),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 32),
                      
                      // Button
                      Container(
                        width: double.infinity,
                        height: 56,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(28),
                          gradient: LinearGradient(
                            colors: [
                              _pages[_currentPage].accentColor,
                              Color.alphaBlend(Colors.black.withValues(alpha: 0.12), _pages[_currentPage].accentColor),
                            ],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: _pages[_currentPage].accentColor.withValues(alpha: 0.35),
                              blurRadius: 18,
                              offset: const Offset(0, 8),
                            )
                          ],
                        ),
                        child: ElevatedButton(
                          onPressed: _nextPage,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.transparent,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shadowColor: Colors.transparent,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(28),
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                _currentPage == _pages.length - 1
                                    ? 'Commencer l\'Aventure'
                                    : 'Continuer',
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.2,
                                ),
                              ),
                              if (_currentPage < _pages.length - 1) ...[
                                const SizedBox(width: 8),
                                const Icon(Icons.arrow_forward_rounded, size: 20),
                              ],
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'Étape ${_currentPage + 1} sur ${_pages.length}',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF9E9E9E),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPage(OnboardingPage page) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const SizedBox(height: 16),
              // Illustration/Card Container
              Container(
                width: double.infinity,
                height: math.min(constraints.maxHeight * 0.52, 380),
                decoration: BoxDecoration(
                  color: page.showProfile ? Colors.transparent : page.backgroundColor,
                  borderRadius: BorderRadius.circular(32),
                  boxShadow: page.showProfile 
                      ? null 
                      : [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.04),
                            blurRadius: 24,
                            offset: const Offset(0, 10),
                          ),
                        ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(32),
                  child: page.showProfile
                      ? _buildProfileCard()
                      : page.showRoleSelection
                          ? _buildRoleSelection()
                          : Center(
                              child: Image.asset(
                                page.image,
                                width: 260,
                                height: 260,
                                fit: BoxFit.contain,
                                errorBuilder: (context, error, stackTrace) {
                                  return _buildPlaceholder(page);
                                },
                              ),
                            ),
                ),
              ),
              const SizedBox(height: 32),
              // Title
              Text(
                page.title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF1B1B1B),
                  letterSpacing: -0.5,
                  height: 1.25,
                ),
              ),
              const SizedBox(height: 14),
              // Description
              Text(
                page.description,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 15,
                  color: Color(0xFF6E6E6E),
                  fontWeight: FontWeight.w500,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        );
      }
    );
  }

  Widget _buildPlaceholder(OnboardingPage page) {
    IconData icon;
    if (page.title.contains('Experts')) {
      icon = Icons.person_search_rounded;
    } else if (page.title.contains('Séquestre')) {
      icon = Icons.security_rounded;
    } else if (page.title.contains('Score')) {
      icon = Icons.star_rounded;
    } else {
      icon = Icons.handshake_rounded;
    }

    return Center(
      child: Container(
        width: 140,
        height: 140,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.85),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: page.accentColor.withValues(alpha: 0.2),
              blurRadius: 20,
              offset: const Offset(0, 8),
            )
          ],
        ),
        child: Icon(
          icon,
          size: 70,
          color: page.accentColor,
        ),
      ),
    );
  }

  Widget _buildProfileCard() {
    return Center(
      child: Container(
        width: double.infinity,
        constraints: const BoxConstraints(maxHeight: 360),
        margin: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
        padding: const EdgeInsets.all(22),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: const Color(0xFFF0E4D3), width: 1.5),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFFD4B37D).withValues(alpha: 0.12),
              blurRadius: 28,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Upper Info
            Row(
              children: [
                // Avatar with premium circle
                Container(
                  width: 60,
                  height: 60,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFDB750).withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFFFDB750), width: 2),
                  ),
                  child: const Center(
                    child: Text('👨‍🔧', style: TextStyle(fontSize: 30)),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Text(
                            'Moussa Traoré',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF1A1A1A),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.all(2),
                            decoration: const BoxDecoration(
                              color: Color(0xFF4CAF50),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(Icons.check, color: Colors.white, size: 12),
                          ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Maître Menuisier • Marcory, Abidjan',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF7A7A7A),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            // Divider
            Container(
              height: 1,
              color: const Color(0xFFF0E4D3),
            ),
            const SizedBox(height: 18),
            // Stats Row
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _buildProsArtisanMetric(
                  'Score ProsArtisan',
                  '98',
                  '/100',
                  const Color(0xFFFDB750),
                ),
                _buildProsArtisanMetric(
                  'Missions',
                  '124',
                  ' faites',
                  const Color(0xFF5B5FEF),
                ),
              ],
            ),
            const SizedBox(height: 18),
            // Mini info cards
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFE8F5E9),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                children: [
                  const Icon(Icons.verified_user_rounded, color: Color(0xFF2E7D32), size: 18),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text(
                      'KYC Approuvé • Garantie Intégrité',
                      style: TextStyle(
                        color: Color(0xFF2E7D32),
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProsArtisanMetric(String label, String value, String suffix, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w900,
            color: Color(0xFF9E9E9E),
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 4),
        Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            Text(
              value,
              style: TextStyle(
                fontSize: 34,
                fontWeight: FontWeight.w900,
                color: color,
                height: 1,
              ),
            ),
            Text(
              suffix,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.bold,
                color: Color(0xFF9E9E9E),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildRoleSelection() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Dynamic bubbles representation
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _buildRoleCircle('Client', '👩‍💼', const Color(0xFFE8EAF6), const Color(0xFF5B5FEF)),
                _buildRoleCircle('Artisan', '👨‍🔧', const Color(0xFFFFF8E1), const Color(0xFFFDB750)),
                _buildRoleCircle('Boutique', '🏭', const Color(0xFFE8F5E9), const Color(0xFF4CAF50)),
              ],
            ),
            const SizedBox(height: 24),
            const Icon(Icons.swap_horiz_rounded, color: Color(0xFF5B5FEF), size: 36),
            const SizedBox(height: 8),
            const Text(
              'Un Écosystème Intégré',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
                color: Color(0xFF1A1A1A),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRoleCircle(String name, String emoji, Color bg, Color border) {
    return Column(
      children: [
        Container(
          width: 76,
          height: 76,
          decoration: BoxDecoration(
            color: bg,
            shape: BoxShape.circle,
            border: Border.all(color: border, width: 2.5),
            boxShadow: [
              BoxShadow(
                color: border.withValues(alpha: 0.18),
                blurRadius: 12,
                offset: const Offset(0, 4),
              )
            ],
          ),
          child: Center(
            child: Text(emoji, style: const TextStyle(fontSize: 32)),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          name,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: Color(0xFF1A1A1A),
          ),
        ),
      ],
    );
  }
}

class _GlowBubble extends StatelessWidget {
  const _GlowBubble({
    required this.size,
    required this.color,
  });

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [
              color,
              color.withValues(alpha: 0),
            ],
          ),
        ),
      ),
    );
  }
}

class OnboardingPage {
  final String image;
  final String title;
  final String description;
  final Color backgroundColor;
  final Color accentColor;
  final bool showProfile;
  final bool showRoleSelection;

  OnboardingPage({
    required this.image,
    required this.title,
    required this.description,
    required this.backgroundColor,
    required this.accentColor,
    this.showProfile = false,
    this.showRoleSelection = false,
  });
}

