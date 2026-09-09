import 'package:flutter/material.dart';

class ServiceIconHelper {
  /// Retourne l'icône Material la plus appropriée pour un secteur ou métier (en français ou clé API).
  static IconData getSectorIcon(String name, [String? iconKey]) {
    final lower = name.toLowerCase();
    final key = iconKey?.toLowerCase() ?? '';

    // Électricité / Énergie
    if (key == 'zap' ||
        lower.contains('électr') ||
        lower.contains('electr') ||
        lower.contains('courant') ||
        lower.contains('solaire') ||
        lower.contains('photovolta')) {
      return Icons.bolt_rounded;
    }

    // Plomberie / Eau
    if (key == 'droplets' ||
        lower.contains('plomb') ||
        lower.contains('eau') ||
        lower.contains('sanitaire') ||
        lower.contains('fuite') ||
        lower.contains('pompage') ||
        lower.contains('forage') ||
        lower.contains('siphon')) {
      return Icons.water_drop_rounded;
    }

    // Maçonnerie / Gros œuvre
    if (key == 'layers' ||
        lower.contains('maçon') ||
        lower.contains('macon') ||
        lower.contains('bâtiment') ||
        lower.contains('batiment') ||
        lower.contains('gros œuvre') ||
        lower.contains('gros oeuvre') ||
        lower.contains('béton') ||
        lower.contains('beton') ||
        lower.contains('coffreur') ||
        lower.contains('ferrailleur')) {
      return Icons.foundation_rounded;
    }

    // Menuiserie / Bois / Métallerie
    if (key == 'scissors' ||
        lower.contains('menuis') ||
        lower.contains('bois') ||
        lower.contains('charpent') ||
        lower.contains('ébénist') ||
        lower.contains('ebenist') ||
        lower.contains('meuble')) {
      return Icons.carpenter_rounded;
    }

    // Peinture & Revêtements
    if (key == 'paintbrush' ||
        lower.contains('peint') ||
        lower.contains('revêt') ||
        lower.contains('revet') ||
        lower.contains('enduit') ||
        lower.contains('crépi') ||
        lower.contains('crepi') ||
        lower.contains('plâtr') ||
        lower.contains('platr')) {
      return Icons.format_paint_rounded;
    }

    // Climatisation / Froid
    if (key == 'wind' ||
        lower.contains('climat') ||
        lower.contains('froid') ||
        lower.contains('split') ||
        lower.contains('frigo') ||
        lower.contains('réfrigér') ||
        lower.contains('refriger') ||
        lower.contains('ventilat')) {
      return Icons.ac_unit_rounded;
    }

    // Serrurerie / Portes / Clés
    if (lower.contains('serrur') ||
        lower.contains('clé') ||
        lower.contains('cle') ||
        lower.contains('verrou') ||
        lower.contains('blindage')) {
      return Icons.lock_outline_rounded;
    }

    // Mécanique & Véhicules
    if (lower.contains('auto') ||
        lower.contains('moto') ||
        lower.contains('mécan') ||
        lower.contains('mecan') ||
        lower.contains('garage') ||
        lower.contains('vidange')) {
      return Icons.directions_car_filled_rounded;
    }

    // Soudure & Métaux
    if (lower.contains('soud') ||
        lower.contains('métal') ||
        lower.contains('metal') ||
        lower.contains('fer') ||
        lower.contains('forge') ||
        lower.contains('forgeron')) {
      return Icons.hardware_rounded;
    }

    // Carrelage / Revêtement de sol
    if (lower.contains('carrel') ||
        lower.contains('mosaï') ||
        lower.contains('mosai') ||
        lower.contains('parquet')) {
      return Icons.grid_view_rounded;
    }

    // Vitrerie / Fenêtres
    if (lower.contains('vitr') || lower.contains('fenêtre') || lower.contains('fenetre')) {
      return Icons.window_rounded;
    }

    // Sécurité / Domotique / Caméras
    if (lower.contains('sécur') ||
        lower.contains('secur') ||
        lower.contains('alarme') ||
        lower.contains('caméra') ||
        lower.contains('camera') ||
        lower.contains('domot')) {
      return Icons.security_rounded;
    }

    // Nettoyage / Entretien
    if (lower.contains('nettoy') ||
        lower.contains('propr') ||
        lower.contains('entretien') ||
        lower.contains('ménage') ||
        lower.contains('menage')) {
      return Icons.cleaning_services_rounded;
    }

    // Jardinage / Espaces verts
    if (lower.contains('jardin') ||
        lower.contains('vert') ||
        lower.contains('plante') ||
        lower.contains('arbre') ||
        lower.contains('paysag')) {
      return Icons.yard_rounded;
    }

    // Couture / Textile
    if (lower.contains('coutur') ||
        lower.contains('confect') ||
        lower.contains('habit') ||
        lower.contains('vêtement') ||
        lower.contains('vetement') ||
        lower.contains('tailleur')) {
      return Icons.checkroom_rounded;
    }

    // Coiffure / Beauté
    if (lower.contains('coiff') ||
        lower.contains('esthét') ||
        lower.contains('esthet') ||
        lower.contains('beauté') ||
        lower.contains('beaute') ||
        lower.contains('barbi')) {
      return Icons.face_retouching_natural_rounded;
    }

    return Icons.handyman_rounded;
  }

  /// Retourne une couleur harmonieuse et contrastée pour le secteur.
  static Color getSectorColor(String name, [String? hexColor]) {
    if (hexColor != null && hexColor.trim().isNotEmpty) {
      try {
        return Color(int.parse(hexColor.replaceFirst('#', '0xFF')));
      } catch (_) {}
    }

    final lower = name.toLowerCase();
    if (lower.contains('électr') || lower.contains('electr')) {
      return const Color(0xFFF59E0B); // Ambre / Orange vif
    }
    if (lower.contains('plomb')) {
      return const Color(0xFF0284C7); // Bleu ciel cyan
    }
    if (lower.contains('maçon') || lower.contains('macon')) {
      return const Color(0xFF64748B); // Gris ardoise solide
    }
    if (lower.contains('menuis')) {
      return const Color(0xFFD97706); // Ocre bois
    }
    if (lower.contains('peint')) {
      return const Color(0xFF8B5CF6); // Violet / Pourpre décoratif
    }
    if (lower.contains('climat') || lower.contains('froid')) {
      return const Color(0xFF0D9488); // Teal menthe glacée
    }
    if (lower.contains('auto') || lower.contains('moto')) {
      return const Color(0xFFEF4444); // Rouge mécanique
    }
    if (lower.contains('soud') || lower.contains('métal')) {
      return const Color(0xFF475569); // Acier foncé
    }
    if (lower.contains('serrur')) {
      return const Color(0xFFEA580C); // Orange cuivre
    }
    if (lower.contains('coutur')) {
      return const Color(0xFFEC4899); // Rose magenta
    }
    if (lower.contains('coiff') || lower.contains('esthét')) {
      return const Color(0xFFF43F5E); // Rose corail
    }
    if (lower.contains('sécur') || lower.contains('secur')) {
      return const Color(0xFF10B981); // Vert émeraude sécurité
    }
    if (lower.contains('nettoy')) {
      return const Color(0xFF06B6D4); // Cyan éclatant
    }
    if (lower.contains('jardin')) {
      return const Color(0xFF16A34A); // Vert forêt
    }

    return const Color(0xFF4F46E5); // Indigo ProsArtisan par défaut
  }
}
