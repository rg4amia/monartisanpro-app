import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/presentation/controllers/devis_controller.dart';

class DevisListPage extends GetView<DevisController> {
  final Mission mission;

  const DevisListPage({super.key, required this.mission});

  @override
  Widget build(BuildContext context) {
    // Set the mission when the page is built
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.setMission(mission);
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Devis reçus'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          // Mission info header
          _buildMissionHeader(),
          
          // Devis list
          Expanded(
            child: Obx(() {
              if (controller.isLoading) {
                return const Center(child: CircularProgressIndicator());
              }

              if (controller.devisList.isEmpty) {
                return _buildEmptyState();
              }

              return ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: controller.devisList.length,
                itemBuilder: (context, index) {
                  final devis = controller.devisList[index];
                  return _buildDevisCard(devis);
                },
              );
            }),
          ),
        ],
      ),
    );
  }

  Widget _buildMissionHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.blue[50],
        border: Border(bottom: BorderSide(color: Colors.grey[300]!)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      mission.category.displayName,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Colors.blue,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      mission.description,
                      style: const TextStyle(fontSize: 14),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              _buildStatusChip(mission.status),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Budget: ${_formatCurrency(mission.budgetMin)} - ${_formatCurrency(mission.budgetMax)}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: Colors.green,
            ),
          ),
          const SizedBox(height: 8),
          Obx(() => Text(
            '${controller.devisList.length}/3 devis reçus',
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey[600],
            ),
          )),
        ],
      ),
    );
  }

  Widget _buildStatusChip(MissionStatus status) {
    Color color;
    String text;
    
    switch (status) {
      case MissionStatus.open:
        color = Colors.green;
        text = 'Ouvert';
        break;
      case MissionStatus.quoted:
        color = Colors.orange;
        text = 'Devis reçus';
        break;
      case MissionStatus.accepted:
        color = Colors.blue;
        text = 'Accepté';
        break;
      case MissionStatus.cancelled:
        color = Colors.red;
        text = 'Annulé';
        break;
    }

    return Chip(
      label: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.receipt_long, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              'Aucun devis reçu',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Les artisans intéressés par votre mission vous enverront leurs devis ici.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[500]),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: () => controller.loadDevisForMission(mission.id),
              icon: const Icon(Icons.refresh),
              label: const Text('Actualiser'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.blue[600],
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDevisCard(Devis devis) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header with artisan info and status
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: Colors.blue[100],
                  child: const Icon(Icons.person, color: Colors.blue),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Artisan ${devis.artisanId.substring(0, 8)}...',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        'Soumis le ${_formatDate(devis.createdAt)}',
                        style: TextStyle(
                          color: Colors.grey[600],
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                _buildDevisStatusChip(devis.status),
              ],
            ),
            
            const SizedBox(height: 16),
            
            // Price breakdown
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Matériel:'),
                      Text(
                        _formatCurrency(devis.materialsAmount),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Main d\'œuvre:'),
                      Text(
                        _formatCurrency(devis.laborAmount),
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  const Divider(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total:',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        _formatCurrency(devis.totalAmount),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: Colors.blue,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 16),
            
            // Line items summary
            if (devis.lineItems.isNotEmpty) ...[
              Text(
                'Détail (${devis.lineItems.length} ligne${devis.lineItems.length > 1 ? 's' : ''})',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              ...devis.lineItems.take(3).map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  children: [
                    Icon(
                      item.type == DevisLineType.material ? Icons.build : Icons.person,
                      size: 16,
                      color: Colors.grey[600],
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${item.description} (${item.quantity}x)',
                        style: const TextStyle(fontSize: 12),
                      ),
                    ),
                    Text(
                      _formatCurrency(item.total),
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              )).toList(),
              
              if (devis.lineItems.length > 3)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    '... et ${devis.lineItems.length - 3} autre${devis.lineItems.length - 3 > 1 ? 's' : ''} ligne${devis.lineItems.length - 3 > 1 ? 's' : ''}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                ),
              
              const SizedBox(height: 16),
            ],
            
            // Action buttons
            if (devis.status == DevisStatus.pending && !devis.isExpired) ...[
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => _showRejectConfirmation(devis),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                        side: const BorderSide(color: Colors.red),
                      ),
                      child: const Text('Rejeter'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => _showAcceptConfirmation(devis),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('Accepter'),
                    ),
                  ),
                ],
              ),
            ] else if (devis.isExpired) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.red[50],
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'Devis expiré',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.red[700],
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
            
            // View details button
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => _showDevisDetails(devis),
              child: const Text('Voir les détails'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDevisStatusChip(DevisStatus status) {
    Color color;
    String text;
    
    switch (status) {
      case DevisStatus.pending:
        color = Colors.orange;
        text = 'En attente';
        break;
      case DevisStatus.accepted:
        color = Colors.green;
        text = 'Accepté';
        break;
      case DevisStatus.rejected:
        color = Colors.red;
        text = 'Rejeté';
        break;
    }

    return Chip(
      label: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
    );
  }

  void _showAcceptConfirmation(Devis devis) {
    Get.dialog(
      AlertDialog(
        title: const Text('Accepter le devis'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Êtes-vous sûr de vouloir accepter ce devis ?'),
            const SizedBox(height: 16),
            Text(
              'Montant total: ${_formatCurrency(devis.totalAmount)}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Cette action déclenchera le processus de séquestre et rejettera automatiquement les autres devis.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              Get.back();
              controller.acceptDevis(devis.id);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
            child: const Text('Accepter'),
          ),
        ],
      ),
    );
  }

  void _showRejectConfirmation(Devis devis) {
    Get.dialog(
      AlertDialog(
        title: const Text('Rejeter le devis'),
        content: const Text('Êtes-vous sûr de vouloir rejeter ce devis ?'),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              Get.back();
              controller.rejectDevis(devis.id);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: const Text('Rejeter'),
          ),
        ],
      ),
    );
  }

  void _showDevisDetails(Devis devis) {
    Get.bottomSheet(
      Container(
        height: Get.height * 0.8,
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Détail du devis',
                  style: Get.textTheme.headlineSmall,
                ),
                const Spacer(),
                IconButton(
                  onPressed: () => Get.back(),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 16),
            
            Expanded(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Artisan info
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Informations artisan',
                              style: TextStyle(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 8),
                            Text('ID: ${devis.artisanId}'),
                            Text('Soumis le: ${_formatDate(devis.createdAt)}'),
                            if (devis.expiresAt != null)
                              Text('Expire le: ${_formatDate(devis.expiresAt!)}'),
                          ],
                        ),
                      ),
                    ),
                    
                    const SizedBox(height: 16),
                    
                    // Line items
                    const Text(
                      'Lignes du devis',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    const SizedBox(height: 8),
                    
                    ...devis.lineItems.map((item) => Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: item.type == DevisLineType.material
                              ? Colors.orange
                              : Colors.blue,
                          child: Icon(
                            item.type == DevisLineType.material
                                ? Icons.build
                                : Icons.person,
                            color: Colors.white,
                            size: 20,
                          ),
                        ),
                        title: Text(item.description),
                        subtitle: Text(
                          '${item.quantity} x ${_formatCurrency(item.unitPrice)}',
                        ),
                        trailing: Text(
                          _formatCurrency(item.total),
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ),
                    )).toList(),
                    
                    const SizedBox(height: 16),
                    
                    // Total summary
                    Card(
                      color: Colors.blue[50],
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Matériel:'),
                                Text(
                                  _formatCurrency(devis.materialsAmount),
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Main d\'œuvre:'),
                                Text(
                                  _formatCurrency(devis.laborAmount),
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                            const Divider(),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  'Total:',
                                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                                ),
                                Text(
                                  _formatCurrency(devis.totalAmount),
                                  style: const TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.blue,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatCurrency(double amount) {
    return '${amount.toStringAsFixed(0).replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (Match m) => '${m[1]} ',
    )} FCFA';
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }
}