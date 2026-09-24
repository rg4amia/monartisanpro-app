import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/mission_model.dart';
import '../../controllers/home_controller.dart';
import '../../views/delivery_route_planner_screen.dart';
import 'driver_delivery_prompts.dart';

Widget buildEmptyDeliveriesCard(String text) {
  return Container(
    padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AppColors.border),
    ),
    child: Center(
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontSize: 13,
          color: AppColors.textSecondary,
          fontStyle: FontStyle.italic,
        ),
      ),
    ),
  );
}

/// La déclaration d'un temps d'attente n'a de sens que tant que la
/// livraison n'est pas terminée — au-delà, la commande quitte de toute
/// façon la liste des courses actives, mais on se protège explicitement de
/// tout statut terminal.
const _waitingSurgeTerminalStatuses = {
  'delivered',
  'received',
  'completed',
  'terminee',
  'cancelled',
  'annulee',
};

bool _canReportWaitingSurge(String rawStatus) =>
    !_waitingSurgeTerminalStatuses.contains(rawStatus);

Widget buildActiveDeliveryCard(
  HomeController controller,
  MissionModel mission,
) {
  final rawStatus = mission.rawStatus;
  final deliveryFee = mission.montantMo > 0
      ? mission.montantMo
      : (mission.montantTotal > 0
          ? (mission.montantTotal * 0.15).toInt()
          : 1500);

  return Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AppColors.border),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: 0.02),
          blurRadius: 8,
          offset: const Offset(0, 3),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'CMD-#${mission.id}',
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
            Text(
              '+ ${Formatters.fcfa(deliveryFee)}',
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.success,
              ),
            ),
          ],
        ),
        const Divider(height: 20),
        _buildDeliveryStep(
          stepNumber: '1',
          title: 'Enlèvement Boutique (Fournisseur)',
          value:
              '${mission.artisanName ?? 'Quincaillerie Centrale'} (${mission.location ?? 'Cocody'})',
        ),
        const SizedBox(height: 12),
        _buildDeliveryStep(
          stepNumber: '2',
          title: 'Livraison Client',
          value:
              '${mission.clientName ?? 'Client'} (${mission.location ?? 'Cocody'})',
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => Get.to(
                  () => DeliveryRoutePlannerScreen(mission: mission),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.driver,
                  side: const BorderSide(color: AppColors.driver),
                  minimumSize: const Size(0, 44),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                icon: const Icon(Icons.map_outlined, size: 18),
                label: const Text(
                  'Itinéraire',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: rawStatus == 'driver_assigned' || rawStatus == 'prepared'
                  ? ElevatedButton.icon(
                      onPressed: () => promptPickupCode(controller, mission),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.warning,
                        foregroundColor: Colors.white,
                        minimumSize: const Size(0, 44),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      icon: const Icon(Icons.check_circle_outline, size: 18),
                      label: const Text(
                        'Enlèvement',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    )
                  : ElevatedButton.icon(
                      onPressed: () => promptDropoffCode(controller, mission),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.success,
                        foregroundColor: Colors.white,
                        minimumSize: const Size(0, 44),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      icon: const Icon(Icons.check_circle, size: 18),
                      label: const Text(
                        'Livraison',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
            ),
          ],
        ),
        if (_canReportWaitingSurge(rawStatus)) ...[
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () => promptWaitingSurge(controller, mission),
              style: TextButton.styleFrom(
                foregroundColor: AppColors.warning,
                padding: const EdgeInsets.symmetric(horizontal: 4),
                minimumSize: const Size(0, 32),
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              icon: const Icon(Icons.hourglass_bottom_rounded, size: 15),
              label: const Text(
                'Signaler un temps d\'attente',
                style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ],
      ],
    ),
  );
}

Widget _buildDeliveryStep({
  required String stepNumber,
  required String title,
  required String value,
}) {
  return Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 18,
        height: 18,
        decoration: const BoxDecoration(
          color: AppColors.primary,
          shape: BoxShape.circle,
        ),
        child: Center(
          child: Text(
            stepNumber,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 10,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ),
      const SizedBox(width: 8),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title.toUpperCase(),
              style: const TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w800,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              value,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

Widget buildAvailableDeliveryCard(
  HomeController controller,
  MissionModel mission,
) {
  final deliveryFee = mission.montantMo > 0
      ? mission.montantMo
      : (mission.montantTotal > 0
          ? (mission.montantTotal * 0.15).toInt()
          : 1500);

  return Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AppColors.border),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Magasin : ${mission.artisanName ?? 'Quincaillerie Centrale'}',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Destination : ${mission.location ?? 'Cocody, Angré'}',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'Matériel : ${mission.description ?? 'Articles divers'}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              Formatters.fcfa(deliveryFee),
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.driver,
              ),
            ),
            const SizedBox(height: 8),
            ElevatedButton(
              onPressed: () async {
                await controller.handleAcceptDelivery(mission);
                unawaited(
                  Get.to(
                    () => DeliveryRoutePlannerScreen(mission: mission),
                  ),
                );
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                minimumSize: const Size(80, 32),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: const Text(
                'Accepter',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}
