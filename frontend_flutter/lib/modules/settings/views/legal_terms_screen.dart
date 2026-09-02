import 'package:flutter/material.dart';
import 'package:get/get.dart';

class LegalTermsScreen extends StatefulWidget {
  final int initialTab; // 0: CGU, 1: Politique de confidentialité

  const LegalTermsScreen({
    super.key,
    this.initialTab = 0,
  });

  @override
  State<LegalTermsScreen> createState() => _LegalTermsScreenState();
}

class _LegalTermsScreenState extends State<LegalTermsScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  static const _gold = Color(0xFFD8A84E);
  static const _goldDeep = Color(0xFFAD6F1D);
  static const _ink = Color(0xFF1F1A17);
  static const _copy = Color(0xFF5C4A3E);
  static const _bg = Color(0xFFF9F7F4);
  static const _cardBg = Colors.white;
  static const _border = Color(0xFFEADBCE);
  static const _green = Color(0xFF1F7A55);

  @override
  void initState() {
    super.initState();
    final args = Get.arguments;
    _tabController = TabController(
      length: 2,
      vsync: this,
      initialIndex: (args is Map && args['tab'] != null)
          ? (args['tab'] as int).clamp(0, 1)
          : widget.initialTab.clamp(0, 1),
    );
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: _cardBg,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        leading: IconButton(
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            size: 19,
            color: _ink,
          ),
          onPressed: () => Get.back(),
        ),
        title: const Text(
          'Documents Légaux',
          style: TextStyle(
            color: _ink,
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: Container(
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFFF0EBE4),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _border),
            ),
            child: TabBar(
              controller: _tabController,
              indicatorSize: TabBarIndicatorSize.tab,
              indicator: BoxDecoration(
                color: _cardBg,
                borderRadius: BorderRadius.circular(10),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              labelColor: _ink,
              unselectedLabelColor: _copy,
              labelStyle:
                  const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
              unselectedLabelStyle:
                  const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              dividerColor: Colors.transparent,
              tabs: const [
                Tab(text: 'CGU ProsArtisan'),
                Tab(text: 'Confidentialité & Données'),
              ],
            ),
          ),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildCguTab(),
          _buildPrivacyTab(),
        ],
      ),
    );
  }

  // ── CGU Tab ──────────────────────────────────────────────────────────────────
  Widget _buildCguTab() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        _buildHeaderCard(
          icon: Icons.gavel_rounded,
          badgeText: 'Cadre Légal & Réglementaire',
          badgeColor: _goldDeep,
          badgeBg: const Color(0xFFFFF7EB),
          title: 'Conditions Générales d\'Utilisation',
          subtitle:
              'ProsArtisan — Plateforme technologique d\'intermédiation et de sécurisation financière en Côte d\'Ivoire.',
          updatedAt: '30 Août 2026',
        ),
        const SizedBox(height: 16),
        _buildArticleCard(
          number: '1',
          title: 'Objet et Définition des Rôles',
          content: [
            _buildParagraph(
              'Nature du Service',
              'ProsArtisan est exclusivement une plateforme technologique d’intermédiation et de sécurisation financière.',
            ),
            _buildParagraph(
              'Indépendance',
              'Aucun lien de subordination n\'existe entre ProsArtisan et les artisans inscrits. ProsArtisan n’est ni employeur, ni maître d’œuvre, ni sous-traitant.',
            ),
            _buildParagraph(
              'Éligibilité',
              'L’utilisation est réservée aux personnes majeures capables de contracter selon le droit ivoirien.',
            ),
          ],
        ),
        _buildArticleCard(
          number: '2',
          title: 'Enrôlement et Identité (KYC)',
          content: [
            _buildParagraph(
              'Vérification Stricte',
              'Tout compte Artisan nécessite la soumission d’une pièce d\'identité valide (CNI, Passeport, Attestation d\'Identité) et d’un numéro de téléphone actif (Mobile Money).',
            ),
            _buildParagraph(
              'Exactitude des Données',
              'L’utilisateur s’engage à fournir des informations réelles. L\'usurpation d\'identité ou la falsification de compétences entraîne une suspension immédiate et irrévocable, avec signalement potentiel aux autorités.',
            ),
          ],
        ),
        _buildArticleCard(
          number: '3',
          title: 'Fonctionnement du Paiement Séquestre',
          content: [
            _buildParagraph(
              'Sécurisation des Fonds',
              'Pour valider une prestation, le Client s\'acquitte du montant total via la plateforme (Mobile Money Wave / Orange Money ou carte bancaire). Ces fonds sont placés sur un compte de cantonnement (séquestre) géré par ProsArtisan.',
            ),
            Container(
              margin: const EdgeInsets.symmetric(vertical: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF2F2),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFFECACA)),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '⚠️ Interdiction stricte du Contournement',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Color(0xFFB91C1C),
                      fontSize: 13,
                    ),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Toute transaction financière de main à main ou transfert direct (pour transport, achat de matériel imprévu, ou acompte) en dehors du système ProsArtisan est strictement interdite. En cas de violation, ProsArtisan décline toute responsabilité et se réserve le droit de bannir les utilisateurs impliqués.',
                    style: TextStyle(
                      color: Color(0xFF7F1D1D),
                      fontSize: 12,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
            _buildParagraph(
              'Libération des Fonds',
              'Les fonds sont transférés à l’Artisan (déduction faite de la commission de la plateforme) uniquement après confirmation de l’achèvement des travaux par le Client via l\'application (ou validation des jalons par code OTP unique).',
            ),
          ],
        ),
        _buildArticleCard(
          number: '4',
          title: 'Engagements et Exécution des Prestations',
          content: [
            _buildParagraph(
              'Obligations de l\'Artisan',
              'Ponctualité, conformité au devis validé, respect du domicile du client, et nettoyage du site après intervention. Tout retard abusif non justifié ou comportement inapproprié affectera son score de fiabilité.',
            ),
            _buildParagraph(
              'Obligations du Client',
              'Fournir des spécifications claires, garantir l\'accès au site d\'intervention, et valider la fin des travaux de bonne foi dès leur achèvement.',
            ),
          ],
        ),
        _buildArticleCard(
          number: '5',
          title: 'Gestion des Litiges et Arbitrage',
          content: [
            _buildParagraph(
              'Gel des Fonds',
              'En cas de désaccord sur la qualité ou l\'achèvement de la prestation, le Client doit déclencher une procédure de litige dans l\'application sous 24 heures. Les fonds restent séquestrés.',
            ),
            _buildParagraph(
              'Preuves Numériques',
              'Les deux parties ont l\'obligation de fournir des preuves via l\'application (photos avant/après géolocalisées, historique des messages internes).',
            ),
            _buildParagraph(
              'Arbitrage ProsArtisan',
              'Le support technique de ProsArtisan intervient comme médiateur de premier niveau pour trancher le litige sur la base des éléments numériques fournis.',
            ),
          ],
        ),
        _buildArticleCard(
          number: '6',
          title: 'Propriété Intellectuelle et Données',
          content: [
            _buildParagraph(
              'Cession de Droits',
              'Les Artisans autorisent ProsArtisan à utiliser les photographies de leurs réalisations téléchargées sur l\'application à des fins de promotion et de marketing.',
            ),
            _buildParagraph(
              'Confidentialité',
              'Les utilisateurs s\'interdisent de réutiliser les données personnelles (numéro de téléphone, adresse physique) obtenues via la plateforme à d\'autres fins que l\'exécution de la prestation convenue.',
            ),
          ],
        ),
        _buildArticleCard(
          number: '7',
          title: 'Limitation de Responsabilité',
          content: [
            _buildParagraph(
              'Garantie Plateforme',
              'ProsArtisan garantit le fonctionnement de l\'infrastructure de mise en relation et la traçabilité des paiements.',
            ),
            _buildParagraph(
              'Exonération',
              'ProsArtisan ne peut être tenu responsable des malfaçons, des dommages matériels ou corporels survenant lors de l\'exécution de la prestation, ni des interruptions de service dues à des pannes de réseaux de télécommunications tiers.',
            ),
          ],
        ),
      ],
    );
  }

  // ── Privacy Tab ──────────────────────────────────────────────────────────────
  Widget _buildPrivacyTab() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        _buildHeaderCard(
          icon: Icons.shield_outlined,
          badgeText: 'Loi n° 2013-450 / ARTCI',
          badgeColor: _green,
          badgeBg: const Color(0xFFE8F5EE),
          title: 'Politique de Confidentialité',
          subtitle:
              'Protection des données personnelles, floutage géographique et conformité réglementaire ivoirienne.',
          updatedAt: '30 Août 2026',
        ),
        const SizedBox(height: 16),
        _buildArticleCard(
          number: '1',
          title: 'Préambule et Responsabilité du Traitement',
          content: [
            const Text(
              'La présente politique définit la manière dont l\'application ProsArtisan collecte, traite, stocke et protège les données à caractère personnel de ses utilisateurs (Clients et Artisans). ProsArtisan agit en tant que responsable de traitement exclusif de cet environnement.',
              style: TextStyle(color: _copy, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFBBF7D0)),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '🔒 Étanchéité institutionnelle absolue',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF166534),
                      fontSize: 13,
                    ),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'L\'infrastructure de ProsArtisan opère dans une étanchéité absolue vis-à-vis de toute application institutionnelle tierce : aucune donnée utilisateur n\'est synchronisée, partagée ou accessible par des démembrements administratifs ou des agences régionales.',
                    style: TextStyle(
                      color: Color(0xFF14532D),
                      fontSize: 12,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        _buildArticleCard(
          number: '2',
          title: 'Nature des Données Collectées',
          content: [
            const Text(
              'L\'architecture de collecte obéit au principe de minimisation (seules les données strictement nécessaires au service sont requises) :',
              style: TextStyle(color: _copy, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 6),
            _buildBulletPoint(
              'Données d’identification',
              'Noms, prénoms, numéros de téléphone (vérifiés par OTP), adresses e-mail. Pour les Artisans : copie numérisée de la pièce d\'identité (CNI, Passeport).',
            ),
            _buildBulletPoint(
              'Données de localisation',
              'Coordonnées GPS collectées uniquement lors de l\'utilisation active de l\'application, afin d\'assurer le matching géographique entre la demande du Client et la position de l\'Artisan (avec floutage de 50 m).',
            ),
            _buildBulletPoint(
              'Données financières',
              'Historique des paiements, identifiants de transactions Mobile Money ou bancaires. ProsArtisan ne stocke pas les codes PIN ou mots de passe des portefeuilles électroniques.',
            ),
            _buildBulletPoint(
              'Données d\'activité et IA',
              'Historique des recherches, photographies des réalisations, évaluations et requêtes textuelles (anonymisées après vectorisation).',
            ),
          ],
        ),
        _buildArticleCard(
          number: '3',
          title: 'Finalités du Traitement',
          content: [
            const Text(
              'Vos données ne sont jamais commercialisées. Elles sont exploitées pour :',
              style: TextStyle(color: _copy, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 6),
            _buildBulletPoint(
              'Authentification & Anti-Fraude',
              'Authentifier les profils et lutter contre la fraude (usurpation d\'identité, blanchiment).',
            ),
            _buildBulletPoint(
              'Matching Sémantique',
              'Générer des correspondances précises via notre moteur de recherche sémantique.',
            ),
            _buildBulletPoint(
              'Séquestre Financier',
              'Garantir la sécurité du mécanisme de paiement sous séquestre.',
            ),
            _buildBulletPoint(
              'Preuves d\'Arbitrage',
              'Fournir des preuves numériques (photos, logs de géolocalisation, historique de chat interne) nécessaires à l\'arbitrage en cas de litige.',
            ),
          ],
        ),
        _buildArticleCard(
          number: '4',
          title: 'Partage et Transfert des Données',
          content: [
            const Text(
              'Dans le cadre exclusif de l\'exécution du service, certaines données chiffrées sont transmises à des tiers de confiance :',
              style: TextStyle(color: _copy, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 6),
            _buildBulletPoint(
              'Agrégateurs de paiement',
              'Wave, Orange Money, MTN, Moov pour l\'exécution et la libération des fonds.',
            ),
            _buildBulletPoint(
              'Fournisseurs d\'infrastructure Cloud',
              'Pour l\'hébergement sécurisé des bases de données et des fichiers médias.',
            ),
            const SizedBox(height: 6),
            const Text(
              'Tous les sous-traitants sont soumis à des clauses de confidentialité strictes. Aucune donnée n\'est transférée en dehors de l\'espace CEDEAO sans garanties de sécurité équivalentes aux normes ARTCI.',
              style: TextStyle(
                color: _copy,
                fontSize: 12,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
        ),
        _buildArticleCard(
          number: '5',
          title: 'Sécurité et Conservation',
          content: [
            const Text(
              'Les flux de données sont sécurisés de bout en bout (protocoles TLS). Les pièces d\'identité et données sensibles sont chiffrées au repos dans nos bases de données.',
              style: TextStyle(color: _copy, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3F4F6),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: _border),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Compte Actif',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 11,
                            color: _ink,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Données courantes',
                          style: TextStyle(fontSize: 10, color: _copy),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3F4F6),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: _border),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '60 Jours',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 11,
                            color: _ink,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Géolocalisation',
                          style: TextStyle(fontSize: 10, color: _copy),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3F4F6),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: _border),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '10 Ans (OHADA)',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 11,
                            color: _ink,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Factures & Séquestre',
                          style: TextStyle(fontSize: 10, color: _copy),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
        _buildArticleCard(
          number: '6',
          title: 'Droits des Utilisateurs & Contact DPO',
          content: [
            const Text(
              'Conformément à la législation en vigueur (Loi n° 2013-450), tout utilisateur dispose d\'un droit d\'accès, de rectification, de limitation et de suppression de ses données ("droit à l\'oubli").',
              style: TextStyle(color: _copy, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFBBF7D0)),
              ),
              child: const Row(
                children: [
                  Icon(
                    Icons.email_outlined,
                    size: 18,
                    color: Color(0xFF166534),
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Contact DPO officiel : dpo@prosartisan.net',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF14532D),
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }

  // ── Shared UI Helpers ────────────────────────────────────────────────────────
  Widget _buildHeaderCard({
    required IconData icon,
    required String badgeText,
    required Color badgeColor,
    required Color badgeBg,
    required String title,
    required String subtitle,
    required String updatedAt,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: badgeBg,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: badgeColor.withValues(alpha: 0.3)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(icon, size: 14, color: badgeColor),
                    const SizedBox(width: 5),
                    Text(
                      badgeText,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: badgeColor,
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              Text(
                'Mise à jour : $updatedAt',
                style: const TextStyle(
                  fontSize: 11,
                  color: Color(0xFF8A7766),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            title,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: _ink,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 12.5,
              color: _copy,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildArticleCard({
    required String number,
    required String title,
    required List<Widget> content,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF7EB),
                  shape: BoxShape.circle,
                  border: Border.all(color: _gold.withValues(alpha: 0.5)),
                ),
                alignment: Alignment.center,
                child: Text(
                  number,
                  style: const TextStyle(
                    color: _goldDeep,
                    fontWeight: FontWeight.w900,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: _ink,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF0EBE4)),
          const SizedBox(height: 12),
          ...content,
        ],
      ),
    );
  }

  Widget _buildParagraph(String label, String body) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(color: _copy, fontSize: 13, height: 1.45),
          children: [
            TextSpan(
              text: '$label : ',
              style: const TextStyle(fontWeight: FontWeight.w800, color: _ink),
            ),
            TextSpan(text: body),
          ],
        ),
      ),
    );
  }

  Widget _buildBulletPoint(String title, String body) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 6, right: 8),
            width: 5,
            height: 5,
            decoration: const BoxDecoration(
              color: _green,
              shape: BoxShape.circle,
            ),
          ),
          Expanded(
            child: RichText(
              text: TextSpan(
                style:
                    const TextStyle(color: _copy, fontSize: 12.5, height: 1.4),
                children: [
                  TextSpan(
                    text: '$title : ',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: _ink,
                    ),
                  ),
                  TextSpan(text: body),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
