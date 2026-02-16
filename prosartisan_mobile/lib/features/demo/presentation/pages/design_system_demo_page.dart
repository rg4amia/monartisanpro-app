import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/routes/app_routes.dart';
import '../../../../shared/models/category_model.dart';
import '../../../../shared/models/provider_model.dart';
import '../../../../shared/widgets/cards/promotional_card.dart';
import '../../../../shared/widgets/cards/category_card.dart';
import '../../../../shared/widgets/cards/service_card.dart';
import '../../../../shared/widgets/badges/rating_badge.dart';
import '../../../../shared/widgets/badges/status_badge.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/buttons/icon_button.dart';
import '../../../../shared/widgets/common/search_bar.dart';
import '../../../../shared/widgets/common/provider_info.dart';
import '../../../home/data/models/service_model.dart';

/// Page de démonstration du design system
/// Montre tous les composants implémentés avec leurs variantes
class DesignSystemDemoPage extends StatefulWidget {
  const DesignSystemDemoPage({super.key});

  @override
  State<DesignSystemDemoPage> createState() => _DesignSystemDemoPageState();
}

class _DesignSystemDemoPageState extends State<DesignSystemDemoPage> {
  String? selectedCategoryId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Design System Demo',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: AppSpacing.screenPaddingAll,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Bouton pour tester la récupération des trades
            _buildSection(
              title: 'API Demo - Trades',
              child: _buildTradesApiDemo(),
            ),

            // Section Typographie
            _buildSection(title: 'Typographie', child: _buildTypographyDemo()),

            // Section Couleurs
            _buildSection(title: 'Couleurs', child: _buildColorsDemo()),

            // Section Cartes Promotionnelles
            _buildSection(
              title: 'Cartes Promotionnelles',
              child: _buildPromotionalCardsDemo(),
            ),

            // Section Cartes de Catégorie
            _buildSection(
              title: 'Cartes de Catégorie',
              child: _buildCategoryCardsDemo(),
            ),

            // Section Cartes de Service
            _buildSection(
              title: 'Cartes de Service',
              child: _buildServiceCardsDemo(),
            ),

            // Section Badges
            _buildSection(title: 'Badges', child: _buildBadgesDemo()),

            // Section Boutons
            _buildSection(title: 'Boutons', child: _buildButtonsDemo()),

            // Section Barre de Recherche
            _buildSection(
              title: 'Barres de Recherche',
              child: _buildSearchBarsDemo(),
            ),

            // Section Info Prestataire
            _buildSection(
              title: 'Informations Prestataire',
              child: _buildProviderInfoDemo(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSection({required String title, required Widget child}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: AppTypography.h3.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: AppSpacing.base),
        child,
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }

  Widget _buildTradesApiDemo() {
    return Column(
      children: [
        PrimaryButton(
          text: 'Tester la récupération des métiers',
          icon: Icons.work,
          onPressed: () {
            Get.toNamed(AppRoutes.tradeSelectionDemo);
          },
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Teste la récupération des secteurs et métiers depuis l\'API backend',
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textSecondary,
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildTypographyDemo() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'H1 - Titre Principal',
          style: AppTypography.h1.copyWith(color: AppColors.textPrimary),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'H2 - Titre Secondaire',
          style: AppTypography.h2.copyWith(color: AppColors.textPrimary),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'H3 - Titre de Section',
          style: AppTypography.h3.copyWith(color: AppColors.textPrimary),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'H4 - Sous-titre',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Body - Texte principal',
          style: AppTypography.body.copyWith(color: AppColors.textPrimary),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Body Small - Texte secondaire',
          style: AppTypography.bodySmall.copyWith(
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Caption - Légende',
          style: AppTypography.caption.copyWith(color: AppColors.textTertiary),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Tiny - Très petit texte',
          style: AppTypography.tiny.copyWith(color: AppColors.textMuted),
        ),
      ],
    );
  }

  Widget _buildColorsDemo() {
    return Wrap(
      spacing: AppSpacing.md,
      runSpacing: AppSpacing.md,
      children: [
        _buildColorSwatch('Primary BG', AppColors.primaryBg),
        _buildColorSwatch('Secondary BG', AppColors.secondaryBg),
        _buildColorSwatch('Card BG', AppColors.cardBg),
        _buildColorSwatch('Elevated BG', AppColors.elevatedBg),
        _buildColorSwatch('Accent Primary', AppColors.accentPrimary),
        _buildColorSwatch('Accent Success', AppColors.accentSuccess),
        _buildColorSwatch('Accent Warning', AppColors.accentWarning),
        _buildColorSwatch('Accent Danger', AppColors.accentDanger),
      ],
    );
  }

  Widget _buildColorSwatch(String name, Color color) {
    return Column(
      children: [
        Container(
          width: 60,
          height: 60,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: AppColors.overlayMedium),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(
          name,
          style: AppTypography.caption.copyWith(color: AppColors.textSecondary),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildPromotionalCardsDemo() {
    return Column(
      children: [
        // Carte standard
        PromotionalCard(
          discount: '20%',
          title: 'Offre Spéciale!',
          subtitle: 'Réduction sur tous les services aujourd\'hui',
          onTap: () => _showSnackBar('Promotion tappée'),
        ),

        const SizedBox(height: AppSpacing.base),

        // Carte compacte
        CompactPromotionalCard(
          text: 'Nouveau service disponible',
          emoji: '🎉',
          onTap: () => _showSnackBar('Promotion compacte tappée'),
        ),

        const SizedBox(height: AppSpacing.base),

        // Carte avec action
        PromotionalCardWithAction(
          discount: '15%',
          title: 'Première commande',
          subtitle: 'Profitez de cette offre de bienvenue',
          buttonText: 'Découvrir',
          onButtonPressed: () => _showSnackBar('Bouton découvrir tappé'),
        ),
      ],
    );
  }

  Widget _buildCategoryCardsDemo() {
    final categories = [
      const CategoryModel(
        id: 'plumbing',
        name: 'Plomberie',
        icon: Icons.plumbing,
        iconColor: AppColors.accentPrimary,
      ),
      const CategoryModel(
        id: 'electrical',
        name: 'Électricité',
        icon: Icons.electrical_services,
        iconColor: AppColors.accentWarning,
      ),
      const CategoryModel(
        id: 'cleaning',
        name: 'Ménage',
        icon: Icons.cleaning_services,
        iconColor: AppColors.accentSuccess,
      ),
    ];

    return Column(
      children: [
        // Grille de catégories
        CategoryGrid(
          categories: categories,
          activeCategory: selectedCategoryId,
          onCategorySelected: (category) {
            setState(() {
              selectedCategoryId = selectedCategoryId == category.id
                  ? null
                  : category.id;
            });
          },
        ),

        const SizedBox(height: AppSpacing.base),

        // Liste horizontale
        HorizontalCategoryList(
          categories: categories,
          activeCategory: selectedCategoryId,
          onCategorySelected: (category) {
            setState(() {
              selectedCategoryId = selectedCategoryId == category.id
                  ? null
                  : category.id;
            });
          },
        ),
      ],
    );
  }

  Widget _buildServiceCardsDemo() {
    final service = ServiceModel(
      id: '1',
      title: 'Réparation de plomberie',
      description:
          'Service de réparation rapide pour tous vos problèmes de plomberie',
      price: 25000,
      currency: 'FCFA',
      rating: 4.8,
      reviewCount: 156,
      provider: const ProviderModel(
        id: 'p1',
        name: 'Jean Dupont',
        role: 'Plombier Expert',
        isVerified: true,
        rating: 4.9,
      ),
      isFavorite: false,
    );

    return Column(
      children: [
        // Carte verticale
        ServiceCard(
          service: service,
          onTap: () => _showSnackBar('Service tappé'),
          onFavoritePressed: () => _showSnackBar('Favori tappé'),
        ),

        const SizedBox(height: AppSpacing.base),

        // Carte horizontale
        HorizontalServiceCard(
          service: service,
          onTap: () => _showSnackBar('Service horizontal tappé'),
          onFavoritePressed: () => _showSnackBar('Favori horizontal tappé'),
        ),
      ],
    );
  }

  Widget _buildBadgesDemo() {
    return Wrap(
      spacing: AppSpacing.md,
      runSpacing: AppSpacing.md,
      children: [
        // Badges de rating
        const RatingBadge(rating: 4.8, reviewCount: 156),
        const RatingBadge(rating: 4.2, reviewCount: 89, compact: true),
        const ColoredRatingBadge(rating: 4.9, reviewCount: 234),
        const SimpleRatingBadge(rating: 4.5),

        // Badges de statut
        const StatusBadge(status: 'completed'),
        const StatusBadge(status: 'pending'),
        const StatusBadge(status: 'cancelled'),
        const StatusBadgeWithIcon(status: 'confirmed'),
        const OutlinedStatusBadge(status: 'in_progress'),
        const PriorityBadge(priority: 'high'),
      ],
    );
  }

  Widget _buildButtonsDemo() {
    return Column(
      children: [
        // Boutons primaires
        Row(
          children: [
            Expanded(
              child: PrimaryButton(
                text: 'Bouton Principal',
                onPressed: () => _showSnackBar('Bouton principal tappé'),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: SecondaryButton(
                text: 'Bouton Secondaire',
                onPressed: () => _showSnackBar('Bouton secondaire tappé'),
              ),
            ),
          ],
        ),

        const SizedBox(height: AppSpacing.base),

        // Boutons avec icônes
        Row(
          children: [
            Expanded(
              child: PrimaryButton(
                text: 'Avec Icône',
                icon: Icons.star,
                onPressed: () => _showSnackBar('Bouton avec icône tappé'),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: GradientButton(
                text: 'Gradient',
                gradientColors: AppColors.promotionalGradient,
                onPressed: () => _showSnackBar('Bouton gradient tappé'),
              ),
            ),
          ],
        ),

        const SizedBox(height: AppSpacing.base),

        // Boutons d'icône
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            CustomIconButton(
              icon: Icons.favorite,
              onPressed: () => _showSnackBar('Icône favori tappée'),
            ),
            PressableIconButton(
              icon: Icons.share,
              onPressed: () => _showSnackBar('Icône partage tappée'),
            ),
            GradientIconButton(
              icon: Icons.add,
              gradientColors: AppColors.promotionalGradient,
              onPressed: () => _showSnackBar('Icône gradient tappée'),
            ),
            ToggleIconButton(
              icon: Icons.bookmark_border,
              activeIcon: Icons.bookmark,
              isActive: false,
              onPressed: () => _showSnackBar('Toggle tappé'),
            ),
          ],
        ),

        const SizedBox(height: AppSpacing.base),

        // Bouton flottant
        FloatingButton(
          text: 'Flottant',
          icon: Icons.add,
          onPressed: () => _showSnackBar('Bouton flottant tappé'),
        ),
      ],
    );
  }

  Widget _buildSearchBarsDemo() {
    return Column(
      children: [
        // Barre standard
        SearchBarWidget(
          hintText: 'Rechercher des services...',
          onChanged: (value) => print('Recherche: $value'),
          onFilterPressed: () => _showSnackBar('Filtre tappé'),
        ),

        const SizedBox(height: AppSpacing.base),

        // Barre compacte
        CompactSearchBar(
          hintText: 'Recherche compacte...',
          onTap: () => _showSnackBar('Recherche compacte tappée'),
          readOnly: true,
        ),

        const SizedBox(height: AppSpacing.base),

        // Barre avec catégories
        SearchBarWithCategories(
          hintText: 'Rechercher par catégorie...',
          categories: ['Plomberie', 'Électricité', 'Ménage'],
          onChanged: (value) => print('Recherche catégorie: $value'),
          onCategoryChanged: (category) => print('Catégorie: $category'),
        ),
      ],
    );
  }

  Widget _buildProviderInfoDemo() {
    const provider = ProviderModel(
      id: 'p1',
      name: 'Jean Dupont',
      role: 'Plombier Expert',
      isVerified: true,
      rating: 4.9,
    );

    return Column(
      children: [
        // Info standard
        ProviderInfo(
          provider: provider,
          showRating: true,
          onTap: () => _showSnackBar('Prestataire tappé'),
        ),

        const SizedBox(height: AppSpacing.base),

        // Info compacte
        CompactProviderInfo(
          provider: provider,
          onTap: () => _showSnackBar('Prestataire compact tappé'),
        ),

        const SizedBox(height: AppSpacing.base),

        // Carte complète
        ProviderCard(
          provider: provider,
          description:
              'Plombier professionnel avec plus de 10 ans d\'expérience',
          completedJobs: 156,
          onTap: () => _showSnackBar('Carte prestataire tappée'),
          onMessageTap: () => _showSnackBar('Message tappé'),
          onCallTap: () => _showSnackBar('Appel tappé'),
        ),
      ],
    );
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: AppColors.accentPrimary,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}
