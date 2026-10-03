import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/history_models.dart';
import '../../../data/models/payout_model.dart';
import '../../../data/repositories/history_repository.dart';
import '../../missions/widgets/tracking/mission_state_history_section.dart';
import '../widgets/paged_history_view.dart';

final DateFormat _day = DateFormat('dd/MM/yyyy', 'fr_FR');

String _on(DateTime? date) => date == null ? '' : _day.format(date);

const List<HistoryFilter> _litigeFilters = [
  HistoryFilter(null, 'Tous'),
  HistoryFilter('ouvert', 'Ouverts'),
  HistoryFilter('en_cours', 'En cours'),
  HistoryFilter('resolu', 'Résolus'),
];

/// « Mes litiges » du client ou de l'artisan : chaque ligne ouvre la fiche.
class MyLitigesScreen extends StatelessWidget {
  const MyLitigesScreen({this.repository, super.key});

  final HistoryRepository? repository;

  @override
  Widget build(BuildContext context) {
    final repo = repository ?? HistoryRepository();

    return PagedHistoryView<LitigeSummary>(
      title: 'Mes litiges',
      filters: _litigeFilters,
      fetch: (filter, page) => repo.myLitiges(statut: filter, page: page),
      emptyTitle: 'Aucun litige',
      emptyMessage:
          'Les litiges ouverts sur vos missions apparaîtront ici, avec leur décision.',
      itemBuilder: (context, litige) => HistoryCard(
        title: litige.motif,
        badge: litige.statutLabel,
        badgeColor: litige.isResolved ? AppColors.success : AppColors.danger,
        lines: [
          'Mission #${litige.missionId} · ouvert le ${_on(litige.createdAt)}',
          if (litige.decisionLabel != null)
            'Décision : ${litige.decisionLabel}',
          if (litige.resolvedAt != null) 'Résolu le ${_on(litige.resolvedAt)}',
        ],
        onTap: () => Get.toNamed(
          Routes.litigeDetail,
          arguments: {'litigeId': litige.id},
        ),
      ),
    );
  }
}

/// Litiges des chantiers que le Référent doit visiter ou a visités. Aucune
/// coordonnée des parties n'y figure.
class ReferentLitigesScreen extends StatelessWidget {
  const ReferentLitigesScreen({this.repository, super.key});

  final HistoryRepository? repository;

  @override
  Widget build(BuildContext context) {
    final repo = repository ?? HistoryRepository();

    return PagedHistoryView<ReferentLitige>(
      title: 'Litiges de ma zone',
      filters: _litigeFilters,
      fetch: (filter, page) => repo.referentLitiges(statut: filter, page: page),
      emptyTitle: 'Aucun litige à visiter',
      emptyMessage:
          'Les litiges des chantiers à visiter, puis ceux que vous avez visités, apparaîtront ici.',
      itemBuilder: (context, litige) {
        final mission = litige.mission;

        return HistoryCard(
          title: litige.motif,
          badge: litige.visitRequired
              ? 'Visite à faire'
              : (litige.visitedByMe ? 'Visité' : litige.statutLabel),
          badgeColor:
              litige.visitRequired ? AppColors.danger : AppColors.success,
          lines: [
            if (mission != null)
              'Chantier #${mission.id} · ${mission.address ?? 'Adresse non renseignée'}',
            litige.description,
            'État : ${litige.statutLabel}'
                '${litige.decisionLabel != null ? ' · ${litige.decisionLabel}' : ''}',
            if (litige.visitedAt != null)
              'Visite enregistrée le ${_on(litige.visitedAt)}',
          ],
          trailing:
              mission == null ? null : Formatters.fcfa(mission.montantTotal),
          onTap: mission == null
              ? null
              : () => Get.toNamed(Routes.missionHistory, arguments: mission.id),
        );
      },
    );
  }
}

/// Inspections réalisées par le Référent connecté.
class ReferentInspectionsScreen extends StatelessWidget {
  const ReferentInspectionsScreen({this.repository, super.key});

  final HistoryRepository? repository;

  @override
  Widget build(BuildContext context) {
    final repo = repository ?? HistoryRepository();

    return PagedHistoryView<ReferentInspection>(
      title: 'Mes inspections réalisées',
      fetch: (_, page) => repo.referentInspections(page: page),
      emptyTitle: 'Aucune inspection réalisée',
      emptyMessage:
          'Les chantiers dont vous avez validé le contrôle sur place apparaîtront ici.',
      itemBuilder: (context, inspection) {
        final mission = inspection.mission;

        return HistoryCard(
          title: 'Chantier #${mission.id}',
          badge: mission.statusLabel,
          lines: [
            mission.description,
            mission.address ?? 'Adresse non renseignée',
            if (inspection.inspectedAt != null)
              'Visite du ${_on(inspection.inspectedAt)}',
          ],
          trailing: Formatters.fcfa(mission.montantTotal),
          onTap: () =>
              Get.toNamed(Routes.missionHistory, arguments: mission.id),
        );
      },
    );
  }
}

const Map<String, String> _deliveryStatusLabels = {
  'delivered': 'Livrée',
  'cancelled': 'Annulée',
  'disputed': 'En litige',
};

/// Courses passées du livreur : livrées, annulées, en litige.
class DriverDeliveriesScreen extends StatelessWidget {
  const DriverDeliveriesScreen({
    this.repository,
    this.initialFilter,
    super.key,
  });

  final HistoryRepository? repository;

  /// `delivered`, `cancelled`, `disputed`, ou `null` pour toutes.
  final String? initialFilter;

  static const List<String> _all = ['delivered', 'cancelled', 'disputed'];

  @override
  Widget build(BuildContext context) {
    final repo = repository ?? HistoryRepository();
    final argument = Get.arguments;
    final filter = initialFilter ?? (argument is String ? argument : null);

    return PagedHistoryView<DeliveryRecord>(
      title: 'Mes courses',
      initialFilter: filter,
      filters: const [
        HistoryFilter(null, 'Toutes'),
        HistoryFilter('delivered', 'Livrées'),
        HistoryFilter('cancelled', 'Annulées'),
        HistoryFilter('disputed', 'En litige'),
      ],
      fetch: (filter, page) => repo.deliveries(
        statuses: filter == null ? _all : [filter],
        page: page,
      ),
      emptyTitle: 'Aucune course',
      emptyMessage:
          'Vos courses livrées, annulées ou en litige apparaîtront ici.',
      itemBuilder: (context, delivery) => HistoryCard(
        title: 'Course #${delivery.id}',
        badge: _deliveryStatusLabels[delivery.status] ?? delivery.status,
        badgeColor: delivery.status == 'delivered'
            ? AppColors.success
            : AppColors.danger,
        lines: [
          [
            if (delivery.supplierName != null) delivery.supplierName!,
            if (delivery.city != null) delivery.city!,
          ].join(' → '),
          _on(delivery.createdAt),
        ],
        trailing: delivery.fare > 0
            ? 'Course : ${Formatters.fcfa(delivery.fare)}'
            : null,
      ),
    );
  }
}

/// Historique complet des versements Mobile Money : aboutis, en cours,
/// échoués. La relance d'un virement échoué reste dans le portefeuille.
class ReceivedPayoutsScreen extends StatelessWidget {
  const ReceivedPayoutsScreen({this.repository, super.key});

  final HistoryRepository? repository;

  @override
  Widget build(BuildContext context) {
    final repo = repository ?? HistoryRepository();

    return PagedHistoryView<PayoutModel>(
      title: 'Historique des versements',
      filters: const [
        HistoryFilter(null, 'Tous'),
        HistoryFilter('verse', 'Versés'),
        HistoryFilter('en_cours', 'En cours'),
        HistoryFilter('echoue', 'Échoués'),
      ],
      fetch: (filter, page) => repo.payouts(statut: filter, page: page),
      emptyTitle: 'Aucun versement',
      emptyMessage:
          'Les virements Mobile Money qui vous sont destinés apparaîtront ici, aboutis ou non.',
      itemBuilder: (context, payout) => HistoryCard(
        title: payout.contextLabel,
        badge: payout.statutLabel,
        badgeColor: payout.isPaid
            ? AppColors.success
            : (payout.isFailed ? AppColors.danger : AppColors.warning),
        lines: [
          'Référence ${payout.reference}',
          [
            payout.provider,
            if (payout.phone != null) payout.phone!,
          ].join(' · '),
          if (payout.paidAt != null) 'Versé le ${_on(payout.paidAt)}',
          if (payout.isFailed && payout.lastError != null) payout.lastError!,
        ],
        trailing: Formatters.fcfa(payout.montant),
      ),
    );
  }
}

/// Litiges des commandes de l'utilisateur (client, fournisseur, livreur),
/// avec la décision rendue.
class OrderDisputesScreen extends StatelessWidget {
  const OrderDisputesScreen({this.repository, super.key});

  final HistoryRepository? repository;

  @override
  Widget build(BuildContext context) {
    final repo = repository ?? HistoryRepository();

    return PagedHistoryView<OrderDisputeRecord>(
      title: 'Litiges de commandes',
      filters: const [
        HistoryFilter(null, 'Tous'),
        HistoryFilter('ouvert', 'En cours'),
        HistoryFilter('resolu', 'Résolus'),
      ],
      fetch: (filter, page) => repo.orderDisputes(statut: filter, page: page),
      emptyTitle: 'Aucun litige de commande',
      emptyMessage:
          'Les commandes contestées apparaîtront ici, avec la décision rendue.',
      itemBuilder: (context, dispute) => HistoryCard(
        title: 'Commande #${dispute.orderId}',
        badge: dispute.statutLabel,
        badgeColor: dispute.isResolved ? AppColors.success : AppColors.danger,
        lines: [
          if (dispute.reason != null) 'Motif : ${dispute.reason}',
          'Ouvert le ${_on(dispute.openedAt)}',
          if (dispute.outcomeLabel != null)
            'Décision : ${dispute.outcomeLabel}',
          if (dispute.refundAmount > 0)
            'Remboursé au client : ${Formatters.fcfa(dispute.refundAmount)}'
                '${dispute.responsibleLabel != null ? ' · responsable : ${dispute.responsibleLabel}' : ''}',
          if (dispute.resolutionNote != null) dispute.resolutionNote!,
          if (dispute.resolvedAt != null) 'Clos le ${_on(dispute.resolvedAt)}',
        ],
        trailing: dispute.orderTotal > 0
            ? 'Articles : ${Formatters.fcfa(dispute.orderTotal)}'
            : null,
      ),
    );
  }
}

/// Historique des états d'un chantier, ouvert par le Référent depuis ses
/// inspections et ses litiges. Argument de route : l'identifiant de la mission.
class MissionHistoryScreen extends StatelessWidget {
  const MissionHistoryScreen({this.missionId, this.repository, super.key});

  final int? missionId;
  final HistoryRepository? repository;

  @override
  Widget build(BuildContext context) {
    final argument = Get.arguments;
    final id = missionId ?? (argument is int ? argument : 0);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text('Chantier #$id'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: MissionStateHistorySection(
          missionId: id,
          repository: repository,
          initiallyOpen: true,
        ),
      ),
    );
  }
}
