import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../../core/network/project_service.dart';
import '../../../../shared/models/artisan_search_model.dart';

class CreateQuoteRequestScreen extends StatefulWidget {
  final ArtisanSearchResult artisan;

  const CreateQuoteRequestScreen({super.key, required this.artisan});

  @override
  State<CreateQuoteRequestScreen> createState() =>
      _CreateQuoteRequestScreenState();
}

class _CreateQuoteRequestScreenState extends State<CreateQuoteRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  final ProjectService _projectService = ProjectService();

  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _budgetMinController = TextEditingController();
  final TextEditingController _budgetMaxController = TextEditingController();

  DateTime? _expectedCompletionDate;
  bool _isLoading = false;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _addressController.dispose();
    _budgetMinController.dispose();
    _budgetMaxController.dispose();
    super.dispose();
  }

  Future<void> _selectDate() async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 7)),
      firstDate: DateTime.now().add(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF1F6FD6),
              onPrimary: Colors.white,
              surface: Colors.white,
              onSurface: Color(0xFF111111),
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() {
        _expectedCompletionDate = picked;
      });
    }
  }

  Future<void> _submitRequest() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _isLoading = true);

    try {
      final response = await _projectService.createProject(
        artisanId: widget.artisan.id,
        tradeId: widget.artisan.tradeId!,
        title: _titleController.text.trim(),
        description: _descriptionController.text.trim(),
        latitude: widget.artisan.latitude ?? 0,
        longitude: widget.artisan.longitude ?? 0,
        address: _addressController.text.trim(),
        budgetMin: _budgetMinController.text.isNotEmpty
            ? double.tryParse(_budgetMinController.text)
            : null,
        budgetMax: _budgetMaxController.text.isNotEmpty
            ? double.tryParse(_budgetMaxController.text)
            : null,
        expectedCompletionDate: _expectedCompletionDate != null
            ? DateFormat('yyyy-MM-dd').format(_expectedCompletionDate!)
            : null,
      );

      setState(() => _isLoading = false);

      if (response.success) {
        Get.back();
        Get.snackbar(
          'Succès',
          'Votre demande de devis a été envoyée à ${widget.artisan.name}',
          backgroundColor: const Color(0xFF2FB344),
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
          borderRadius: 12,
          margin: const EdgeInsets.all(16),
          icon: const Icon(Icons.check_circle, color: Colors.white),
        );
      } else {
        Get.snackbar(
          'Erreur',
          response.message ?? 'Impossible d\'envoyer la demande',
          backgroundColor: const Color(0xFFE5484D),
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
          borderRadius: 12,
          margin: const EdgeInsets.all(16),
        );
      }
    } catch (e) {
      setState(() => _isLoading = false);
      Get.snackbar(
        'Erreur',
        'Une erreur est survenue',
        backgroundColor: const Color(0xFFE5484D),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
        borderRadius: 12,
        margin: const EdgeInsets.all(16),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6F8),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Demander un devis',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w600,
            color: Color(0xFF111111),
          ),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF111111)),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            // Artisan Info Card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: const Color(0xFFF2F2F2),
                    backgroundImage: widget.artisan.avatar != null
                        ? NetworkImage(widget.artisan.avatar!)
                        : null,
                    child: widget.artisan.avatar == null
                        ? const Icon(Icons.person, color: Color(0xFF888888))
                        : null,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.artisan.name,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF111111),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          widget.artisan.tradeName ?? 'Artisan',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w400,
                            color: Color(0xFF888888),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (widget.artisan.averageRating != null)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF5A524).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.star_rounded,
                            size: 16,
                            color: Color(0xFFF5A524),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            widget.artisan.averageRating!.toStringAsFixed(1),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFFF5A524),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Title Field
            _buildLabel('Titre du projet'),
            const SizedBox(height: 8),
            _buildTextField(
              controller: _titleController,
              hint: 'Ex: Réparation de fuite d\'eau',
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Veuillez entrer un titre';
                }
                return null;
              },
            ),
            const SizedBox(height: 20),

            // Description Field
            _buildLabel('Description détaillée'),
            const SizedBox(height: 8),
            _buildTextField(
              controller: _descriptionController,
              hint: 'Décrivez en détail les travaux à réaliser...',
              maxLines: 5,
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Veuillez décrire les travaux';
                }
                if (value.trim().length < 20) {
                  return 'Description trop courte (min. 20 caractères)';
                }
                return null;
              },
            ),
            const SizedBox(height: 20),

            // Address Field
            _buildLabel('Adresse du chantier'),
            const SizedBox(height: 8),
            _buildTextField(
              controller: _addressController,
              hint: 'Ex: Cocody, Angré 8ème tranche',
              prefixIcon: Icons.location_on_outlined,
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Veuillez entrer l\'adresse';
                }
                return null;
              },
            ),
            const SizedBox(height: 20),

            // Budget Range
            _buildLabel('Budget estimé (optionnel)'),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _buildTextField(
                    controller: _budgetMinController,
                    hint: 'Min',
                    keyboardType: TextInputType.number,
                    suffixText: 'FCFA',
                  ),
                ),
                const SizedBox(width: 12),
                const Text(
                  '—',
                  style: TextStyle(fontSize: 16, color: Color(0xFF888888)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _buildTextField(
                    controller: _budgetMaxController,
                    hint: 'Max',
                    keyboardType: TextInputType.number,
                    suffixText: 'FCFA',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Expected Completion Date
            _buildLabel('Date de fin souhaitée (optionnel)'),
            const SizedBox(height: 8),
            InkWell(
              onTap: _selectDate,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFDADADA)),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.calendar_today_outlined,
                      color: Color(0xFF888888),
                      size: 20,
                    ),
                    const SizedBox(width: 12),
                    Text(
                      _expectedCompletionDate != null
                          ? DateFormat(
                              'dd MMMM yyyy',
                              'fr_FR',
                            ).format(_expectedCompletionDate!)
                          : 'Sélectionner une date',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w400,
                        color: _expectedCompletionDate != null
                            ? const Color(0xFF111111)
                            : const Color(0xFF888888),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 32),

            // Info Box
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFF3B82F6).withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: const Color(0xFF3B82F6).withValues(alpha: 0.2),
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.info_outline_rounded,
                    color: Color(0xFF3B82F6),
                    size: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'L\'artisan recevra votre demande et vous enverra un devis détaillé. Vous pourrez l\'accepter ou le refuser.',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w400,
                        color: const Color(0xFF3B82F6).withValues(alpha: 0.9),
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),

            // Submit Button
            Container(
              height: 56,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF1F6FD6), Color(0xFF1A5FC0)],
                ),
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF1F6FD6).withValues(alpha: 0.3),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: _isLoading ? null : _submitRequest,
                  borderRadius: BorderRadius.circular(12),
                  child: Center(
                    child: _isLoading
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(
                              valueColor: AlwaysStoppedAnimation<Color>(
                                Colors.white,
                              ),
                              strokeWidth: 2,
                            ),
                          )
                        : const Text(
                            'Envoyer la demande',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                              color: Colors.white,
                            ),
                          ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        color: Color(0xFF111111),
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String hint,
    int maxLines = 1,
    TextInputType? keyboardType,
    IconData? prefixIcon,
    String? suffixText,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      maxLines: maxLines,
      keyboardType: keyboardType,
      validator: validator,
      style: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w400,
        color: Color(0xFF111111),
      ),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w400,
          color: Color(0xFF888888),
        ),
        prefixIcon: prefixIcon != null
            ? Icon(prefixIcon, color: const Color(0xFF888888), size: 20)
            : null,
        suffixText: suffixText,
        suffixStyle: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w500,
          color: Color(0xFF888888),
        ),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.all(16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFDADADA)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFDADADA)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFF1F6FD6), width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE5484D)),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE5484D), width: 2),
        ),
      ),
    );
  }
}
