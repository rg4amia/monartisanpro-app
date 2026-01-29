import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';

/// Barre de recherche personnalisée selon le design system
class SearchBarWidget extends StatefulWidget {
  final String? hintText;
  final String? initialValue;
  final Function(String)? onChanged;
  final Function(String)? onSubmitted;
  final VoidCallback? onFilterPressed;
  final bool showFilter;
  final bool enabled;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final TextEditingController? controller;

  const SearchBarWidget({
    super.key,
    this.hintText,
    this.initialValue,
    this.onChanged,
    this.onSubmitted,
    this.onFilterPressed,
    this.showFilter = true,
    this.enabled = true,
    this.prefixIcon,
    this.suffixIcon,
    this.controller,
  });

  @override
  State<SearchBarWidget> createState() => _SearchBarWidgetState();
}

class _SearchBarWidgetState extends State<SearchBarWidget> {
  late TextEditingController _controller;
  bool _isFocused = false;

  @override
  void initState() {
    super.initState();
    _controller = widget.controller ?? TextEditingController();
    if (widget.initialValue != null) {
      _controller.text = widget.initialValue!;
    }
  }

  @override
  void dispose() {
    if (widget.controller == null) {
      _controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Champ de recherche
        Expanded(
          child: Container(
            height: AppSpacing.searchBarHeight,
            decoration: BoxDecoration(
              color: AppColors.cardBg,
              borderRadius: AppRadius.searchBarRadius,
              border: Border.all(
                color: _isFocused
                    ? AppColors.accentPrimary
                    : AppColors.overlayMedium,
                width: _isFocused ? 2 : 1,
              ),
              boxShadow: _isFocused ? AppShadows.inputFocused : null,
            ),
            child: SizedBox(
              height: AppSpacing.inputHeight,
              child: TextField(
                controller: _controller,
                enabled: widget.enabled,
                onChanged: widget.onChanged,
                onSubmitted: widget.onSubmitted,
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
                decoration: InputDecoration(
                  hintText:
                      widget.hintText ?? 'Rechercher tous les services...',
                  hintStyle: AppTypography.placeholder.copyWith(
                    color: AppColors.textMuted,
                  ),
                  prefixIcon:
                      widget.prefixIcon ??
                      const Icon(Icons.search, color: AppColors.textMuted),
                  suffixIcon:
                      widget.suffixIcon ??
                      (_controller.text.isNotEmpty
                          ? IconButton(
                              icon: const Icon(
                                Icons.clear,
                                color: AppColors.textMuted,
                              ),
                              onPressed: () {
                                _controller.clear();
                                widget.onChanged?.call('');
                              },
                            )
                          : null),
                  border: InputBorder.none,
                  contentPadding: AppSpacing.symmetric(
                    horizontal: AppSpacing.searchBarHorizontalPadding,
                    vertical: AppSpacing.searchBarVerticalPadding,
                  ),
                ),
                onTap: () {
                  setState(() {
                    _isFocused = true;
                  });
                },
                onTapOutside: (event) {
                  setState(() {
                    _isFocused = false;
                  });
                  FocusScope.of(context).unfocus();
                },
              ),
            ),
          ),
        ),

        // Bouton filtre
        if (widget.showFilter) ...[
          const SizedBox(width: AppSpacing.md),
          GestureDetector(
            onTap: widget.onFilterPressed,
            child: Container(
              width: AppSpacing.searchBarHeight,
              height: AppSpacing.searchBarHeight,
              decoration: BoxDecoration(
                color: AppColors.accentPrimary,
                borderRadius: AppRadius.searchBarRadius,
                boxShadow: AppShadows.button,
              ),
              child: const Icon(
                Icons.tune,
                color: Colors.white,
                size: AppSpacing.iconSize,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// Barre de recherche avec suggestions
class SearchBarWithSuggestions extends StatefulWidget {
  final String? hintText;
  final List<String> suggestions;
  final Function(String)? onChanged;
  final Function(String)? onSubmitted;
  final Function(String)? onSuggestionSelected;
  final VoidCallback? onFilterPressed;
  final bool showFilter;

  const SearchBarWithSuggestions({
    super.key,
    this.hintText,
    required this.suggestions,
    this.onChanged,
    this.onSubmitted,
    this.onSuggestionSelected,
    this.onFilterPressed,
    this.showFilter = true,
  });

  @override
  State<SearchBarWithSuggestions> createState() =>
      _SearchBarWithSuggestionsState();
}

class _SearchBarWithSuggestionsState extends State<SearchBarWithSuggestions> {
  final TextEditingController _controller = TextEditingController();
  final FocusNode _focusNode = FocusNode();
  List<String> _filteredSuggestions = [];
  bool _showSuggestions = false;

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() {
      setState(() {
        _showSuggestions =
            _focusNode.hasFocus && _filteredSuggestions.isNotEmpty;
      });
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _filterSuggestions(String query) {
    setState(() {
      if (query.isEmpty) {
        _filteredSuggestions = [];
      } else {
        _filteredSuggestions = widget.suggestions
            .where(
              (suggestion) =>
                  suggestion.toLowerCase().contains(query.toLowerCase()),
            )
            .take(5)
            .toList();
      }
      _showSuggestions = _focusNode.hasFocus && _filteredSuggestions.isNotEmpty;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Barre de recherche
        SearchBarWidget(
          controller: _controller,
          hintText: widget.hintText,
          onChanged: (value) {
            _filterSuggestions(value);
            widget.onChanged?.call(value);
          },
          onSubmitted: widget.onSubmitted,
          onFilterPressed: widget.onFilterPressed,
          showFilter: widget.showFilter,
        ),

        // Suggestions
        if (_showSuggestions)
          Container(
            margin: const EdgeInsets.only(top: AppSpacing.sm),
            decoration: BoxDecoration(
              color: AppColors.cardBg,
              borderRadius: AppRadius.cardRadius,
              boxShadow: AppShadows.card,
            ),
            child: ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _filteredSuggestions.length,
              separatorBuilder: (context, index) =>
                  Divider(height: 1, color: AppColors.overlayMedium),
              itemBuilder: (context, index) {
                final suggestion = _filteredSuggestions[index];
                return ListTile(
                  leading: const Icon(
                    Icons.search,
                    color: AppColors.textMuted,
                    size: 20,
                  ),
                  title: Text(
                    suggestion,
                    style: AppTypography.body.copyWith(
                      color: AppColors.textPrimary,
                    ),
                  ),
                  onTap: () {
                    _controller.text = suggestion;
                    setState(() {
                      _showSuggestions = false;
                    });
                    _focusNode.unfocus();
                    widget.onSuggestionSelected?.call(suggestion);
                  },
                );
              },
            ),
          ),
      ],
    );
  }
}

/// Barre de recherche compacte (pour headers)
class CompactSearchBar extends StatelessWidget {
  final String? hintText;
  final Function(String)? onChanged;
  final Function(String)? onSubmitted;
  final VoidCallback? onTap;
  final bool readOnly;

  const CompactSearchBar({
    super.key,
    this.hintText,
    this.onChanged,
    this.onSubmitted,
    this.onTap,
    this.readOnly = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: readOnly ? onTap : null,
      child: Container(
        height: 40,
        padding: AppSpacing.symmetric(horizontal: AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.overlayLight,
          borderRadius: AppRadius.circular(20),
        ),
        child: Row(
          children: [
            const Icon(Icons.search, color: AppColors.textMuted, size: 20),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: readOnly
                  ? Text(
                      hintText ?? 'Rechercher...',
                      style: AppTypography.bodySmall.copyWith(
                        color: AppColors.textMuted,
                      ),
                    )
                  : TextField(
                      onChanged: onChanged,
                      onSubmitted: onSubmitted,
                      style: AppTypography.bodySmall.copyWith(
                        color: AppColors.textPrimary,
                      ),
                      decoration: InputDecoration(
                        hintText: hintText ?? 'Rechercher...',
                        hintStyle: AppTypography.bodySmall.copyWith(
                          color: AppColors.textMuted,
                        ),
                        border: InputBorder.none,
                        contentPadding: EdgeInsets.zero,
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Barre de recherche avec catégories
class SearchBarWithCategories extends StatefulWidget {
  final String? hintText;
  final List<String> categories;
  final String? selectedCategory;
  final Function(String)? onChanged;
  final Function(String)? onSubmitted;
  final Function(String?)? onCategoryChanged;
  final VoidCallback? onFilterPressed;

  const SearchBarWithCategories({
    super.key,
    this.hintText,
    required this.categories,
    this.selectedCategory,
    this.onChanged,
    this.onSubmitted,
    this.onCategoryChanged,
    this.onFilterPressed,
  });

  @override
  State<SearchBarWithCategories> createState() =>
      _SearchBarWithCategoriesState();
}

class _SearchBarWithCategoriesState extends State<SearchBarWithCategories> {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Barre de recherche
        SearchBarWidget(
          hintText: widget.hintText,
          onChanged: widget.onChanged,
          onSubmitted: widget.onSubmitted,
          onFilterPressed: widget.onFilterPressed,
        ),

        const SizedBox(height: AppSpacing.md),

        // Catégories
        SizedBox(
          height: 40,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: AppSpacing.screenPaddingHorizontal,
            itemCount: widget.categories.length + 1,
            separatorBuilder: (context, index) =>
                const SizedBox(width: AppSpacing.md),
            itemBuilder: (context, index) {
              if (index == 0) {
                // Option "Tous"
                final isSelected = widget.selectedCategory == null;
                return GestureDetector(
                  onTap: () => widget.onCategoryChanged?.call(null),
                  child: Container(
                    padding: AppSpacing.symmetric(
                      horizontal: AppSpacing.base,
                      vertical: AppSpacing.sm,
                    ),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppColors.accentPrimary
                          : AppColors.overlayLight,
                      borderRadius: AppRadius.circular(20),
                    ),
                    child: Text(
                      'Tous',
                      style: AppTypography.bodySmall.copyWith(
                        color: isSelected
                            ? Colors.white
                            : AppColors.textPrimary,
                        fontWeight: isSelected
                            ? FontWeight.w600
                            : FontWeight.normal,
                      ),
                    ),
                  ),
                );
              }

              final category = widget.categories[index - 1];
              final isSelected = widget.selectedCategory == category;

              return GestureDetector(
                onTap: () => widget.onCategoryChanged?.call(category),
                child: Container(
                  padding: AppSpacing.symmetric(
                    horizontal: AppSpacing.base,
                    vertical: AppSpacing.sm,
                  ),
                  decoration: BoxDecoration(
                    color: isSelected
                        ? AppColors.accentPrimary
                        : AppColors.overlayLight,
                    borderRadius: AppRadius.circular(20),
                  ),
                  child: Text(
                    category,
                    style: AppTypography.bodySmall.copyWith(
                      color: isSelected ? Colors.white : AppColors.textPrimary,
                      fontWeight: isSelected
                          ? FontWeight.w600
                          : FontWeight.normal,
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
