import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/network/payment_service.dart';

class PaymentScreen extends StatefulWidget {
  final int projectId;
  final int quoteId;
  final double amount;

  const PaymentScreen({
    super.key,
    required this.projectId,
    required this.quoteId,
    required this.amount,
  });

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  final PaymentService _paymentService = PaymentService();

  WebViewController? _webViewController;
  bool _isLoading = true;
  bool _isProcessing = false;
  String? _paymentUrl;
  String? _transactionId;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _initializePayment();
  }

  Future<void> _initializePayment() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await _paymentService.initializePayment(
        projectId: widget.projectId,
        quoteId: widget.quoteId,
        amount: widget.amount,
      );

      if (response.success && response.data != null) {
        setState(() {
          _paymentUrl = response.data!.paymentUrl;
          _transactionId = response.data!.transactionId;
          _isLoading = false;
        });

        _setupWebView();
      } else {
        setState(() {
          _errorMessage = response.message ?? 'Impossible d\'initialiser le paiement';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Erreur de connexion. Veuillez réessayer.';
        _isLoading = false;
      });
    }
  }

  void _setupWebView() {
    _webViewController = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            print('Page started loading: $url');
          },
          onPageFinished: (url) {
            print('Page finished loading: $url');
            _checkPaymentCallback(url);
          },
          onWebResourceError: (error) {
            print('WebView error: ${error.description}');
          },
          onNavigationRequest: (request) {
            print('Navigation request: ${request.url}');
            _checkPaymentCallback(request.url);
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(_paymentUrl!));
  }

  void _checkPaymentCallback(String url) {
    // Check if the URL is the return URL from CinetPay
    if (url.contains('/payment/callback') || url.contains('/payment/success')) {
      final uri = Uri.parse(url);
      final status = uri.queryParameters['status'];
      final transactionId = uri.queryParameters['transaction_id'] ?? _transactionId;

      if (status == 'ACCEPTED' || status == 'success') {
        _verifyPayment(transactionId ?? _transactionId!);
      } else if (status == 'REFUSED' || status == 'failed') {
        _handlePaymentFailure('Paiement refusé');
      } else if (status == 'CANCELLED') {
        _handlePaymentCancellation();
      }
    }
  }

  Future<void> _verifyPayment(String transactionId) async {
    if (_isProcessing) return;

    setState(() => _isProcessing = true);

    try {
      final response = await _paymentService.verifyPayment(transactionId);

      if (response.success && response.data != null) {
        final status = response.data!.status;

        if (status == 'completed' || status == 'success') {
          _handlePaymentSuccess();
        } else if (status == 'pending') {
          // Payment is still processing
          Get.snackbar(
            'Paiement en cours',
            'Votre paiement est en cours de traitement',
            backgroundColor: AppColors.warning,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
            duration: const Duration(seconds: 5),
          );
          // Wait and retry verification
          await Future.delayed(const Duration(seconds: 3));
          _verifyPayment(transactionId);
        } else {
          _handlePaymentFailure('Paiement non confirmé');
        }
      } else {
        _handlePaymentFailure(response.message ?? 'Erreur de vérification');
      }
    } catch (e) {
      _handlePaymentFailure('Erreur de vérification du paiement');
    } finally {
      setState(() => _isProcessing = false);
    }
  }

  void _handlePaymentSuccess() {
    Get.back(result: true);
    Get.snackbar(
      'Paiement réussi',
      'Votre paiement a été effectué avec succès. Le projet peut démarrer!',
      backgroundColor: AppColors.success,
      colorText: Colors.white,
      snackPosition: SnackPosition.TOP,
      duration: const Duration(seconds: 5),
    );
  }

  void _handlePaymentFailure(String message) {
    Get.dialog(
      AlertDialog(
        title: Row(
          children: [
            Icon(Icons.error_outline, color: AppColors.error),
            const SizedBox(width: Spacing.md),
            const Text('Paiement échoué'),
          ],
        ),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () {
              Get.back();
              Get.back(result: false);
            },
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              Get.back();
              _initializePayment();
            },
            child: const Text('Réessayer'),
          ),
        ],
      ),
    );
  }

  void _handlePaymentCancellation() {
    Get.dialog(
      AlertDialog(
        title: const Text('Paiement annulé'),
        content: const Text('Vous avez annulé le paiement. Voulez-vous réessayer?'),
        actions: [
          TextButton(
            onPressed: () {
              Get.back();
              Get.back(result: false);
            },
            child: const Text('Non'),
          ),
          ElevatedButton(
            onPressed: () {
              Get.back();
              _initializePayment();
            },
            child: const Text('Oui, réessayer'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Paiement'),
        actions: [
          if (!_isLoading && !_isProcessing)
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: _initializePayment,
              tooltip: 'Rafraîchir',
            ),
        ],
      ),
      body: Stack(
        children: [
          if (_isLoading)
            Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const CircularProgressIndicator(),
                  const SizedBox(height: Spacing.lg),
                  Text(
                    'Initialisation du paiement...',
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  const SizedBox(height: Spacing.md),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: Spacing.xxxl),
                    child: Text(
                      'Montant: ${widget.amount.toStringAsFixed(0)} FCFA',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                    ),
                  ),
                ],
              ),
            )
          else if (_errorMessage != null)
            Center(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.screenPadding),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.error_outline,
                      size: 64,
                      color: AppColors.error,
                    ),
                    const SizedBox(height: Spacing.lg),
                    Text(
                      'Erreur',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            color: AppColors.error,
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const SizedBox(height: Spacing.md),
                    Text(
                      _errorMessage!,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyLarge,
                    ),
                    const SizedBox(height: Spacing.xl),
                    ElevatedButton.icon(
                      onPressed: _initializePayment,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Réessayer'),
                    ),
                    const SizedBox(height: Spacing.md),
                    OutlinedButton(
                      onPressed: () => Get.back(result: false),
                      child: const Text('Annuler'),
                    ),
                  ],
                ),
              ),
            )
          else if (_webViewController != null)
            Column(
              children: [
                // Payment Info Banner
                Container(
                  padding: const EdgeInsets.all(Spacing.base),
                  color: AppColors.lightBackground,
                  child: Row(
                    children: [
                      Icon(
                        Icons.lock_outline,
                        color: AppColors.success,
                        size: 20,
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Paiement sécurisé',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    fontWeight: FontWeight.bold,
                                  ),
                            ),
                            Text(
                              'Montant: ${widget.amount.toStringAsFixed(0)} FCFA',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: AppColors.lightTextSecondary,
                                  ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                // WebView
                Expanded(
                  child: WebViewWidget(controller: _webViewController!),
                ),
              ],
            ),

          // Processing overlay
          if (_isProcessing)
            Container(
              color: Colors.black.withValues(alpha: 0.7),
              child: Center(
                child: Card(
                  margin: const EdgeInsets.all(Spacing.xxxl),
                  child: Padding(
                    padding: const EdgeInsets.all(Spacing.xl),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const CircularProgressIndicator(),
                        const SizedBox(height: Spacing.lg),
                        Text(
                          'Vérification du paiement...',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        const SizedBox(height: Spacing.md),
                        Text(
                          'Veuillez patienter',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: AppColors.lightTextSecondary,
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _webViewController = null;
    super.dispose();
  }
}
