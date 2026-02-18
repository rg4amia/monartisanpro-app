import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../../../../shared/controllers/dispute_controller.dart';
import '../../../../shared/models/dispute_model.dart';

class CreateDisputeScreen extends StatefulWidget {
  final int? projectId;

  const CreateDisputeScreen({Key? key, this.projectId}) : super(key: key);

  @override
  State<CreateDisputeScreen> createState() => _CreateDisputeScreenState();
}

class _CreateDisputeScreenState extends State<CreateDisputeScreen> {
  final _formKey = GlobalKey<FormState>();
  final disputeController = Get.find<DisputeController>();

  int? _selectedProjectId;
  String _selectedType = 'quality';
  String _selectedPriority = 'medium';
  final _reasonController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _evidenceFiles = <XFile>[].obs;

  @override
  void initState() {
    super.initState();
    _selectedProjectId = widget.projectId;
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Créer un Litige'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildInfoCard(),
            const SizedBox(height: 24),
            _buildDisputeTypeSection(),
            const SizedBox(height: 24),
            _buildPrioritySection(),
            const SizedBox(height: 24),
            _buildReasonField(),
            const SizedBox(height: 16),
            _buildDescriptionField(),
            const SizedBox(height: 24),
            _buildEvidenceSection(),
            const SizedBox(height: 32),
            _buildSubmitButton(),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoCard() {
    return Card(
      color: Colors.blue[50],
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.info_outline, color: Colors.blue[700]),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'Un litige sera examiné par notre équipe dans les 24-48h',
                style: TextStyle(color: Colors.blue[900]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDisputeTypeSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Type de litige *',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _buildTypeChip('quality', 'Qualité', Icons.star),
            _buildTypeChip('payment', 'Paiement', Icons.payment),
            _buildTypeChip('delay', 'Retard', Icons.schedule),
            _buildTypeChip('fraud', 'Fraude', Icons.warning),
            _buildTypeChip('other', 'Autre', Icons.more_horiz),
          ],
        ),
      ],
    );
  }

  Widget _buildTypeChip(String value, String label, IconData icon) {
    final isSelected = _selectedType == value;
    return FilterChip(
      selected: isSelected,
      label: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18),
          const SizedBox(width: 6),
          Text(label),
        ],
      ),
      onSelected: (selected) {
        setState(() => _selectedType = value);
      },
      selectedColor: Colors.blue[100],
      checkmarkColor: Colors.blue[900],
    );
  }

  Widget _buildPrioritySection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Priorité *',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: _buildPriorityCard('low', 'Basse', Colors.green)),
            const SizedBox(width: 8),
            Expanded(child: _buildPriorityCard('medium', 'Moyenne', Colors.orange)),
            const SizedBox(width: 8),
            Expanded(child: _buildPriorityCard('high', 'Haute', Colors.red)),
          ],
        ),
      ],
    );
  }

  Widget _buildPriorityCard(String value, String label, Color color) {
    final isSelected = _selectedPriority == value;
    return InkWell(
      onTap: () => setState(() => _selectedPriority = value),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? color.withOpacity(0.1) : Colors.grey[100],
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: isSelected ? color : Colors.grey[300]!,
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Column(
          children: [
            Icon(
              isSelected ? Icons.check_circle : Icons.circle_outlined,
              color: isSelected ? color : Colors.grey,
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                color: isSelected ? color : Colors.grey[700],
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildReasonField() {
    return TextFormField(
      controller: _reasonController,
      decoration: const InputDecoration(
        labelText: 'Raison du litige *',
        hintText: 'Ex: Travail non conforme aux spécifications',
        border: OutlineInputBorder(),
        prefixIcon: Icon(Icons.title),
      ),
      maxLength: 255,
      validator: (value) {
        if (value == null || value.isEmpty) {
          return 'Veuillez entrer la raison du litige';
        }
        return null;
      },
    );
  }

  Widget _buildDescriptionField() {
    return TextFormField(
      controller: _descriptionController,
      decoration: const InputDecoration(
        labelText: 'Description détaillée *',
        hintText: 'Décrivez le problème en détail...',
        border: OutlineInputBorder(),
        alignLabelWithHint: true,
      ),
      maxLines: 6,
      validator: (value) {
        if (value == null || value.isEmpty) {
          return 'Veuillez décrire le problème';
        }
        if (value.length < 20) {
          return 'La description doit contenir au moins 20 caractères';
        }
        return null;
      },
    );
  }

  Widget _buildEvidenceSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Preuves (Photos)',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'Ajoutez jusqu\'à 5 photos comme preuves',
          style: TextStyle(color: Colors.grey[600], fontSize: 14),
        ),
        const SizedBox(height: 12),
        Obx(() => _evidenceFiles.isEmpty
            ? _buildAddEvidenceButton()
            : _buildEvidenceGrid()),
      ],
    );
  }

  Widget _buildAddEvidenceButton() {
    return OutlinedButton.icon(
      onPressed: _addEvidence,
      icon: const Icon(Icons.add_photo_alternate),
      label: const Text('Ajouter des photos'),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(double.infinity, 50),
        side: BorderSide(color: Colors.grey[300]!),
      ),
    );
  }

  Widget _buildEvidenceGrid() {
    return Column(
      children: [
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            crossAxisSpacing: 8,
            mainAxisSpacing: 8,
          ),
          itemCount: _evidenceFiles.length,
          itemBuilder: (context, index) {
            return Stack(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.file(
                    File(_evidenceFiles[index].path),
                    fit: BoxFit.cover,
                    width: double.infinity,
                    height: double.infinity,
                  ),
                ),
                Positioned(
                  top: 4,
                  right: 4,
                  child: InkWell(
                    onTap: () => _evidenceFiles.removeAt(index),
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: Colors.red,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.close,
                        size: 16,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
        if (_evidenceFiles.length < 5) ...[
          const SizedBox(height: 12),
          TextButton.icon(
            onPressed: _addEvidence,
            icon: const Icon(Icons.add),
            label: const Text('Ajouter plus de photos'),
          ),
        ],
      ],
    );
  }

  Widget _buildSubmitButton() {
    return Obx(() => ElevatedButton(
          onPressed: disputeController.isLoading.value ? null : _submitDispute,
          style: ElevatedButton.styleFrom(
            minimumSize: const Size(double.infinity, 50),
            backgroundColor: Colors.red,
          ),
          child: disputeController.isLoading.value
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                )
              : const Text(
                  'Soumettre le Litige',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
        ));
  }

  Future<void> _addEvidence() async {
    if (_evidenceFiles.length >= 5) {
      Get.snackbar(
        'Limite atteinte',
        'Vous pouvez ajouter jusqu\'à 5 photos',
        snackPosition: SnackPosition.BOTTOM,
      );
      return;
    }

    final images = await disputeController.pickImages();
    final remainingSlots = 5 - _evidenceFiles.length;
    final imagesToAdd = images.take(remainingSlots).toList();

    _evidenceFiles.addAll(imagesToAdd);

    if (images.length > remainingSlots) {
      Get.snackbar(
        'Information',
        'Seulement ${remainingSlots} photo(s) ont été ajoutée(s)',
        snackPosition: SnackPosition.BOTTOM,
      );
    }
  }

  Future<void> _submitDispute() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_selectedProjectId == null) {
      Get.snackbar(
        'Erreur',
        'Veuillez sélectionner un projet',
        snackPosition: SnackPosition.BOTTOM,
      );
      return;
    }

    final request = CreateDisputeRequest(
      projectId: _selectedProjectId!,
      disputeType: _selectedType,
      reason: _reasonController.text,
      description: _descriptionController.text,
      priority: _selectedPriority,
      evidence: _evidenceFiles.map((file) => file.path).toList(),
    );

    final success = await disputeController.createDispute(request);

    if (success) {
      Get.back();
    }
  }
}
