import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// Carte affichant un code de validation (retrait ou réception).
///
/// Le code est la seule preuve qu'une remise a physiquement eu lieu : son
/// détenteur le communique à l'autre partie **après** avoir remis ou reçu la
/// marchandise. D'où trois partis pris :
///
///  - **Masqué par défaut.** Au comptoir, le livreur se tient à côté du
///    fournisseur ; un code affiché en permanence se lit par-dessus l'épaule,
///    avant même la remise. La révélation doit être un geste délibéré.
///  - **Re-masqué automatiquement** après [revealDuration], pour que le code
///    ne reste pas exposé sur un écran posé sur un comptoir.
///  - **Non copiable.** Un code que l'on peut coller dans une messagerie cesse
///    d'être une preuve de présence ; le dire de vive voix, c'est la preuve.
///
/// Le code n'est pas passé en paramètre mais récupéré par [onReveal] au moment
/// de la révélation : il ne transite donc pas dans les listes de commandes,
/// qui sont mises en cache sur le disque.
class CodeVerificationCard extends StatefulWidget {
  const CodeVerificationCard({
    super.key,
    required this.title,
    required this.instruction,
    required this.onReveal,
    this.orderLabel,
    this.accentColor = AppColors.primary,
    this.backgroundColor = AppColors.secondary,
    this.revealDuration = const Duration(seconds: 30),
  });

  /// Ex. « Code de retrait ».
  final String title;

  /// Consigne d'usage. L'ordre des gestes *est* le modèle de sécurité :
  /// communiquer le code avant la remise annule la garantie.
  final String instruction;

  /// Récupère le code. Retourne `null` si l'utilisateur n'y a pas droit.
  final Future<String?> Function() onReveal;

  /// Ex. « Commande #42 ».
  final String? orderLabel;

  final Color accentColor;
  final Color backgroundColor;
  final Duration revealDuration;

  @override
  State<CodeVerificationCard> createState() => _CodeVerificationCardState();
}

class _CodeVerificationCardState extends State<CodeVerificationCard> {
  String? _code;
  bool _loading = false;
  String? _error;
  Timer? _hideTimer;
  Timer? _tickTimer;
  int _secondsLeft = 0;

  @override
  void dispose() {
    _hideTimer?.cancel();
    _tickTimer?.cancel();
    super.dispose();
  }

  Future<void> _reveal() async {
    if (_loading) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    String? code;
    String? error;
    try {
      code = await widget.onReveal();
      if (code == null || code.trim().isEmpty) {
        error = 'Code indisponible pour le moment.';
      }
    } catch (_) {
      error = 'Impossible de récupérer le code. Vérifiez votre connexion.';
    }

    if (!mounted) return;

    setState(() {
      _loading = false;
      _error = error;
      _code = error == null ? code : null;
    });

    if (error == null) {
      _startHideCountdown();
    }
  }

  void _startHideCountdown() {
    _hideTimer?.cancel();
    _tickTimer?.cancel();

    _secondsLeft = widget.revealDuration.inSeconds;

    _tickTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() => _secondsLeft = (_secondsLeft - 1).clamp(0, 1 << 30));
    });

    _hideTimer = Timer(widget.revealDuration, () {
      if (!mounted) return;
      _tickTimer?.cancel();
      setState(() => _code = null);
    });
  }

  void _hideNow() {
    _hideTimer?.cancel();
    _tickTimer?.cancel();
    setState(() => _code = null);
  }

  /// Seuls les chiffres du code sont lus à voix haute ; le libellé complet
  /// (« LIVREUR-4821 ») reste affiché en dessous pour lever toute ambiguïté.
  String _spokenDigits(String code) {
    final digits = code.replaceAll(RegExp(r'[^0-9]'), '');

    return digits.isEmpty ? code : digits;
  }

  @override
  Widget build(BuildContext context) {
    final revealed = _code != null;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: widget.backgroundColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: widget.accentColor.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                revealed ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
                size: 18,
                color: widget.accentColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  widget.title.toUpperCase(),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8,
                    color: widget.accentColor,
                  ),
                ),
              ),
              if (widget.orderLabel != null)
                Text(
                  widget.orderLabel!,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          _buildCodeArea(revealed),
          const SizedBox(height: 12),
          Text(
            widget.instruction,
            style: const TextStyle(
              fontSize: 12.5,
              height: 1.4,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(
              _error!,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: AppColors.danger,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCodeArea(bool revealed) {
    if (!revealed) {
      return Row(
        children: [
          Expanded(
            child: Container(
              height: 62,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppColors.border),
              ),
              child: Text(
                '● ● ● ●',
                style: TextStyle(
                  fontSize: 24,
                  letterSpacing: 6,
                  color: AppColors.textSecondary.withValues(alpha: 0.6),
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          SizedBox(
            height: 62,
            child: ElevatedButton(
              onPressed: _loading ? null : _reveal,
              style: ElevatedButton.styleFrom(
                backgroundColor: widget.accentColor,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _loading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text(
                      'Révéler',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
            ),
          ),
        ],
      );
    }

    final code = _code!;

    return Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: widget.accentColor, width: 1.5),
          ),
          child: Column(
            children: [
              // Gros, espacé, à lire à voix haute en extérieur.
              Text(
                _spokenDigits(code),
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 40,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 10,
                  height: 1.1,
                  color: widget.accentColor,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
              const SizedBox(height: 2),
              Text(
                code,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 1,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Icon(Icons.timer_outlined,
                size: 15, color: AppColors.textSecondary,),
            const SizedBox(width: 4),
            Text(
              'Masqué dans ${_secondsLeft}s',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: AppColors.textSecondary,
              ),
            ),
            const Spacer(),
            TextButton(
              onPressed: _hideNow,
              style: TextButton.styleFrom(
                foregroundColor: widget.accentColor,
                padding: const EdgeInsets.symmetric(horizontal: 8),
                minimumSize: const Size(0, 32),
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: const Text(
                'Masquer',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
