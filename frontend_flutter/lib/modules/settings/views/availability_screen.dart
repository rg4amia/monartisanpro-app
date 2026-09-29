import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../data/models/artisan_availability_model.dart';
import '../controllers/availability_controller.dart';

/// « Ma disponibilité » de l'artisan (Chantier 15) : ce qui est publié dans
/// l'annuaire du site, la demande en attente ou refusée, et le formulaire.
class AvailabilityScreen extends GetView<AvailabilityController> {
  const AvailabilityScreen({super.key});

  String _date(DateTime date) =>
      '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Ma disponibilité')),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.errorMsg.value != null) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(controller.errorMsg.value!, textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  FilledButton(
                    onPressed: controller.load,
                    child: const Text('Réessayer'),
                  ),
                ],
              ),
            ),
          );
        }

        final overview = controller.overview.value;
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(
              'Votre disponibilité apparaît dans l\'annuaire du site ProsArtisan après validation par nos équipes. '
              'En attendant, la version déjà publiée reste affichée.',
            ),
            const SizedBox(height: 16),
            _StatusCard(
              title: 'Publiée dans l\'annuaire',
              availability: overview?.published,
              emptyText: 'Aucune disponibilité publiée pour l\'instant.',
            ),
            if (overview?.pending != null)
              _StatusCard(
                title: 'En attente de validation',
                availability: overview!.pending,
                color: Colors.amber.shade50,
              ),
            if (overview?.lastRejected != null)
              _StatusCard(
                title: 'Dernière demande refusée',
                availability: overview!.lastRejected,
                color: Colors.red.shade50,
                note: overview.lastRejected!.rejectionReason == null
                    ? null
                    : 'Motif : ${overview.lastRejected!.rejectionReason}',
              ),
            const Divider(height: 32),
            Text(
              overview?.pending != null
                  ? 'Modifier ma demande'
                  : 'Déclarer ma disponibilité',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: kAvailabilityStatusLabels.entries
                  .map(
                    (entry) => ChoiceChip(
                      label: Text(entry.value),
                      selected: controller.status.value == entry.key,
                      onSelected: (_) => controller.setStatus(entry.key),
                    ),
                  )
                  .toList(),
            ),
            if (controller.needsUntilDate) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                icon: const Icon(Icons.event_outlined),
                label: Text(
                  controller.untilDate.value == null
                      ? 'Choisir la date de retour'
                      : 'Jusqu\'au ${_date(controller.untilDate.value!)}',
                ),
                onPressed: () async {
                  final now = DateTime.now();
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: controller.untilDate.value ?? now,
                    firstDate: DateTime(now.year, now.month, now.day),
                    lastDate: now.add(const Duration(days: 365)),
                  );
                  if (picked != null) controller.untilDate.value = picked;
                },
              ),
            ],
            const SizedBox(height: 16),
            Text(
              'Jours et horaires habituels',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            for (var i = 0; i < controller.slots.length; i++)
              _SlotRow(index: i, slot: controller.slots[i]),
            if (controller.slots.length < AvailabilityController.maxSlots)
              TextButton.icon(
                icon: const Icon(Icons.add),
                label: const Text('Ajouter une plage horaire'),
                onPressed: controller.addSlot,
              ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('J\'interviens la nuit'),
              value: controller.nightWork.value,
              onChanged: (value) => controller.nightWork.value = value,
            ),
            if (controller.submitError.value != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  controller.submitError.value!,
                  style: TextStyle(color: Colors.red.shade700),
                ),
              ),
            if (controller.submitSuccess.value != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  controller.submitSuccess.value!,
                  style: TextStyle(color: Colors.green.shade800),
                ),
              ),
            FilledButton(
              onPressed: controller.canSubmit ? controller.submit : null,
              child: controller.isSubmitting.value
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Envoyer pour validation'),
            ),
          ],
        );
      }),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.title,
    required this.availability,
    this.emptyText,
    this.color,
    this.note,
  });

  final String title;
  final ArtisanAvailability? availability;
  final String? emptyText;
  final Color? color;
  final String? note;

  @override
  Widget build(BuildContext context) {
    final value = availability;
    return Card(
      color: color,
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 4),
            if (value == null)
              Text(emptyText ?? '—')
            else ...[
              Text(
                value.label,
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              Text(value.scheduleSummary ?? 'Horaires non précisés'),
              if (value.nightWork) const Text('Intervient la nuit'),
            ],
            if (note != null) ...[
              const SizedBox(height: 4),
              Text(note!),
            ],
          ],
        ),
      ),
    );
  }
}

class _SlotRow extends GetView<AvailabilityController> {
  const _SlotRow({required this.index, required this.slot});

  final int index;
  final AvailabilitySlot slot;

  Future<String?> _pickTime(BuildContext context, String current) async {
    final parts = current.split(':');
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(
        hour: int.tryParse(parts.first) ?? 8,
        minute: int.tryParse(parts.length > 1 ? parts[1] : '0') ?? 0,
      ),
    );
    if (picked == null) return null;
    return '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        DropdownButton<int>(
          value: slot.day,
          items: kAvailabilityDayLabels.entries
              .map(
                (entry) => DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value),
                ),
              )
              .toList(),
          onChanged: (day) {
            if (day != null) {
              controller.updateSlot(index, slot.copyWith(day: day));
            }
          },
        ),
        const SizedBox(width: 8),
        TextButton(
          onPressed: () async {
            final value = await _pickTime(context, slot.start);
            if (value != null) {
              controller.updateSlot(index, slot.copyWith(start: value));
            }
          },
          child: Text(slot.start),
        ),
        const Text('à'),
        TextButton(
          onPressed: () async {
            final value = await _pickTime(context, slot.end);
            if (value != null) {
              controller.updateSlot(index, slot.copyWith(end: value));
            }
          },
          child: Text(slot.end),
        ),
        const Spacer(),
        IconButton(
          tooltip: 'Retirer cette plage',
          icon: const Icon(Icons.delete_outline),
          onPressed: () => controller.removeSlot(index),
        ),
      ],
    );
  }
}
