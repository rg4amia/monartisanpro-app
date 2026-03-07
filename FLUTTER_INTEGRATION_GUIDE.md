# 📱 Guide d'Intégration Flutter - ProsArtisan

## 🎯 Vue d'ensemble

Ce guide détaille l'intégration Flutter pour les fonctionnalités backend implémentées :
1. **Paiements Wave & Orange Money**
2. **Photos géolocalisées (Jalons & J-Codes)**
3. **Workflow Référent** (à venir)
4. **Fiche d'intervention + Signature** (à venir)

---

## 1️⃣ Intégration Paiements Mobile Money

### 📦 Dépendances à ajouter

```yaml
# pubspec.yaml
dependencies:
  http: ^1.1.0
  url_launcher: ^6.2.2  # Pour ouvrir les URLs de paiement
  webview_flutter: ^4.4.4  # Alternative : webview dans l'app
```

### 🔧 Service de Paiement

Créer `lib/services/payment_service.dart` :

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

enum PaymentProvider { wave, orangeMoney }

class PaymentService {
  final String baseUrl;
  final String Function() getToken;

  PaymentService({
    required this.baseUrl,
    required this.getToken,
  });

  /// Initier un paiement
  Future<Map<String, dynamic>> initiatePayment({
    required int missionId,
    required int montant,
    required PaymentProvider provider,
    required String phone,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/v1/payments/initiate'),
      headers: {
        'Authorization': 'Bearer ${getToken()}',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'mission_id': missionId,
        'montant': montant,
        'provider': provider == PaymentProvider.wave ? 'wave' : 'orange_money',
        'phone': phone,
      }),
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Erreur initiation paiement: ${response.body}');
    }
  }

  /// Vérifier le statut d'un paiement
  Future<Map<String, dynamic>> checkPaymentStatus(int transactionId) async {
    final response = await http.get(
      Uri.parse('$baseUrl/api/v1/payments/$transactionId/status'),
      headers: {
        'Authorization': 'Bearer ${getToken()}',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Erreur vérification statut: ${response.body}');
    }
  }

  /// Obtenir l'historique des paiements
  Future<List<dynamic>> getPaymentHistory({int limit = 20}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/api/v1/payments/history?limit=$limit'),
      headers: {
        'Authorization': 'Bearer ${getToken()}',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['data'] as List<dynamic>;
    } else {
      throw Exception('Erreur récupération historique: ${response.body}');
    }
  }
}
```

### 📱 UI de Paiement

Créer `lib/screens/payment_screen.dart` :

```dart
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/payment_service.dart';
import 'dart:async';

class PaymentScreen extends StatefulWidget {
  final int missionId;
  final int montant;

  const PaymentScreen({
    Key? key,
    required this.missionId,
    required this.montant,
  }) : super(key: key);

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  PaymentProvider _selectedProvider = PaymentProvider.wave;
  final _phoneController = TextEditingController();
  bool _isLoading = false;
  int? _transactionId;
  Timer? _pollingTimer;

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _initiatePayment() async {
    if (_phoneController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Veuillez entrer votre numéro')),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final paymentService = PaymentService(
        baseUrl: 'https://api.prosartisan.ci',
        getToken: () => 'YOUR_AUTH_TOKEN', // À récupérer depuis votre AuthProvider
      );

      final result = await paymentService.initiatePayment(
        missionId: widget.missionId,
        montant: widget.montant,
        provider: _selectedProvider,
        phone: _phoneController.text,
      );

      _transactionId = result['data']['transaction_id'];
      final paymentUrl = result['data']['payment_url'];

      // Ouvrir l'app Wave ou Orange Money
      final uri = Uri.parse(paymentUrl);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);

        // Démarrer le polling du statut
        _startPaymentStatusPolling();
      } else {
        throw Exception('Impossible d\'ouvrir l\'application de paiement');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Erreur: $e')),
      );
    } finally {
      setState(() => _isLoading = false);
    }
  }

  void _startPaymentStatusPolling() {
    _pollingTimer = Timer.periodic(const Duration(seconds: 3), (timer) async {
      if (_transactionId == null) {
        timer.cancel();
        return;
      }

      try {
        final paymentService = PaymentService(
          baseUrl: 'https://api.prosartisan.ci',
          getToken: () => 'YOUR_AUTH_TOKEN',
        );

        final result = await paymentService.checkPaymentStatus(_transactionId!);
        final status = result['data']['status'];

        if (status == 'confirme') {
          timer.cancel();
          _showPaymentSuccess();
        } else if (status == 'echoue') {
          timer.cancel();
          _showPaymentFailure();
        }
      } catch (e) {
        print('Erreur polling: $e');
      }
    });

    // Timeout après 5 minutes
    Future.delayed(const Duration(minutes: 5), () {
      _pollingTimer?.cancel();
    });
  }

  void _showPaymentSuccess() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('✅ Paiement réussi'),
        content: const Text('Votre paiement a été confirmé. La mission est maintenant financée !'),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(context).pop(); // Fermer dialog
              Navigator.of(context).pop(); // Retour écran précédent
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showPaymentFailure() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('❌ Paiement échoué'),
        content: const Text('Le paiement n\'a pas pu être complété. Veuillez réessayer.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Paiement')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Montant à payer: ${widget.montant} FCFA',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 24),
            const Text('Choisissez votre moyen de paiement:'),
            const SizedBox(height: 16),
            RadioListTile<PaymentProvider>(
              title: const Text('Wave CI'),
              value: PaymentProvider.wave,
              groupValue: _selectedProvider,
              onChanged: (value) => setState(() => _selectedProvider = value!),
            ),
            RadioListTile<PaymentProvider>(
              title: const Text('Orange Money CI'),
              value: PaymentProvider.orangeMoney,
              groupValue: _selectedProvider,
              onChanged: (value) => setState(() => _selectedProvider = value!),
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _phoneController,
              decoration: const InputDecoration(
                labelText: 'Numéro de téléphone',
                hintText: '07 XX XX XX XX',
                prefixText: '+225 ',
              ),
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 32),
            ElevatedButton(
              onPressed: _isLoading ? null : _initiatePayment,
              child: _isLoading
                  ? const CircularProgressIndicator()
                  : const Text('Payer maintenant'),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## 2️⃣ Intégration Photos Géolocalisées

### 📦 Dépendances à ajouter

```yaml
# pubspec.yaml
dependencies:
  image_picker: ^1.0.5
  geolocator: ^10.1.0
  permission_handler: ^11.1.0
  http: ^1.1.0
  path: ^1.8.3
```

### 🔧 Permissions (Android)

```xml
<!-- android/app/src/main/AndroidManifest.xml -->
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

### 🔧 Permissions (iOS)

```xml
<!-- ios/Runner/Info.plist -->
<key>NSCameraUsageDescription</key>
<string>L'application a besoin d'accéder à l'appareil photo pour prendre des photos preuves</string>
<key>NSLocationWhenInUseUsageDescription</key>
<string>L'application a besoin de votre localisation pour géolocaliser les photos</string>
```

### 🔧 Service Photo

Créer `lib/services/photo_service.dart` :

```dart
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

class PhotoService {
  final String baseUrl;
  final String Function() getToken;
  final ImagePicker _picker = ImagePicker();

  PhotoService({
    required this.baseUrl,
    required this.getToken,
  });

  /// Demander les permissions
  Future<bool> requestPermissions() async {
    final cameraStatus = await Permission.camera.request();
    final locationStatus = await Permission.location.request();

    return cameraStatus.isGranted && locationStatus.isGranted;
  }

  /// Obtenir la position GPS actuelle
  Future<Position> getCurrentPosition() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw Exception('Le service de localisation est désactivé');
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw Exception('Permission de localisation refusée');
      }
    }

    if (permission == LocationPermission.deniedForever) {
      throw Exception('Permission de localisation refusée définitivement');
    }

    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }

  /// Prendre une photo avec l'appareil
  Future<File?> takePicture() async {
    final XFile? photo = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 85,
      maxWidth: 1920,
      maxHeight: 1080,
    );

    return photo != null ? File(photo.path) : null;
  }

  /// Upload photos pour un jalon
  Future<Map<String, dynamic>> uploadJalonPhotos({
    required int jalonId,
    required List<Map<String, dynamic>> photos, // [{'file': File, 'latitude': double, 'longitude': double, 'description': String?}]
  }) async {
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/api/v1/jalons/$jalonId/photos'),
    );

    request.headers['Authorization'] = 'Bearer ${getToken()}';
    request.headers['Accept'] = 'application/json';

    for (int i = 0; i < photos.length; i++) {
      final photo = photos[i];
      request.files.add(
        await http.MultipartFile.fromPath(
          'photos[$i][photo]',
          photo['file'].path,
        ),
      );
      request.fields['photos[$i][latitude]'] = photo['latitude'].toString();
      request.fields['photos[$i][longitude]'] = photo['longitude'].toString();
      if (photo['description'] != null) {
        request.fields['photos[$i][description]'] = photo['description'];
      }
    }

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Erreur upload photos: ${response.body}');
    }
  }

  /// Upload photo matériaux pour un J-Code
  Future<Map<String, dynamic>> uploadJCodePhoto({
    required int jcodeId,
    required File photo,
    required double latitude,
    required double longitude,
  }) async {
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/api/v1/jcodes/$jcodeId/photo-materiaux'),
    );

    request.headers['Authorization'] = 'Bearer ${getToken()}';
    request.headers['Accept'] = 'application/json';

    request.files.add(
      await http.MultipartFile.fromPath('photo', photo.path),
    );
    request.fields['latitude'] = latitude.toString();
    request.fields['longitude'] = longitude.toString();

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Erreur upload photo: ${response.body}');
    }
  }
}
```

### 📱 UI Capture Photo (Jalon)

Créer `lib/screens/jalon_photo_capture_screen.dart` :

```dart
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../services/photo_service.dart';

class JalonPhotoCaptureScreen extends StatefulWidget {
  final int jalonId;

  const JalonPhotoCaptureScreen({Key? key, required this.jalonId}) : super(key: key);

  @override
  State<JalonPhotoCaptureScreen> createState() => _JalonPhotoCaptureScreenState();
}

class _JalonPhotoCaptureScreenState extends State<JalonPhotoCaptureScreen> {
  final PhotoService _photoService = PhotoService(
    baseUrl: 'https://api.prosartisan.ci',
    getToken: () => 'YOUR_AUTH_TOKEN',
  );

  List<Map<String, dynamic>> _photos = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _requestPermissions();
  }

  Future<void> _requestPermissions() async {
    final granted = await _photoService.requestPermissions();
    if (!granted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Permissions requises pour continuer')),
      );
    }
  }

  Future<void> _addPhoto() async {
    try {
      // Prendre la photo
      final File? photoFile = await _photoService.takePicture();
      if (photoFile == null) return;

      // Obtenir la position GPS
      setState(() => _isLoading = true);
      final Position position = await _photoService.getCurrentPosition();

      setState(() {
        _photos.add({
          'file': photoFile,
          'latitude': position.latitude,
          'longitude': position.longitude,
          'description': null,
        });
        _isLoading = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Photo ajoutée (GPS: ${position.latitude.toStringAsFixed(6)}, ${position.longitude.toStringAsFixed(6)})')),
      );
    } catch (e) {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Erreur: $e')),
      );
    }
  }

  Future<void> _uploadPhotos() async {
    if (_photos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Aucune photo à uploader')),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final result = await _photoService.uploadJalonPhotos(
        jalonId: widget.jalonId,
        photos: _photos,
      );

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result['message'])),
      );

      Navigator.of(context).pop(true); // Retour avec succès
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Erreur upload: $e')),
      );
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Photos de preuve'),
        actions: [
          if (_photos.isNotEmpty && !_isLoading)
            TextButton(
              onPressed: _uploadPhotos,
              child: const Text('Envoyer', style: TextStyle(color: Colors.white)),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Expanded(
                  child: _photos.isEmpty
                      ? const Center(child: Text('Aucune photo ajoutée'))
                      : ListView.builder(
                          itemCount: _photos.length,
                          itemBuilder: (context, index) {
                            final photo = _photos[index];
                            return Card(
                              child: ListTile(
                                leading: Image.file(
                                  photo['file'],
                                  width: 60,
                                  height: 60,
                                  fit: BoxFit.cover,
                                ),
                                title: Text('Photo ${index + 1}'),
                                subtitle: Text(
                                  'GPS: ${photo['latitude'].toStringAsFixed(6)}, ${photo['longitude'].toStringAsFixed(6)}',
                                ),
                                trailing: IconButton(
                                  icon: const Icon(Icons.delete, color: Colors.red),
                                  onPressed: () {
                                    setState(() => _photos.removeAt(index));
                                  },
                                ),
                              ),
                            );
                          },
                        ),
                ),
                Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: ElevatedButton.icon(
                    onPressed: _photos.length < 5 ? _addPhoto : null,
                    icon: const Icon(Icons.camera_alt),
                    label: Text('Ajouter une photo (${_photos.length}/5)'),
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size(double.infinity, 50),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
```

---

## 3️⃣ Workflow Complet

### Exemple d'intégration dans une mission :

```dart
// 1. Client accepte le devis
await devisService.acceptDevis(devisId);

// 2. Client initie le paiement
Navigator.push(
  context,
  MaterialPageRoute(
    builder: (context) => PaymentScreen(
      missionId: mission.id,
      montant: mission.montantTotal,
    ),
  ),
);

// 3. Artisan soumet un jalon avec photos
Navigator.push(
  context,
  MaterialPageRoute(
    builder: (context) => JalonPhotoCaptureScreen(jalonId: jalon.id),
  ),
);

// 4. Client reçoit OTP et valide
await jalonService.validateOtp(jalonId, otpCode);
```

---

## 📝 Notes importantes

### Gestion des erreurs
- Toujours gérer les exceptions réseau
- Afficher des messages utilisateur clairs
- Logger les erreurs pour debug

### Performance
- Compresser les images avant upload (déjà fait avec `imageQuality: 85`)
- Limiter la taille max (1920x1080)
- Utiliser un loader pendant les uploads

### Sécurité
- Stocker le token de manière sécurisée (flutter_secure_storage)
- Valider les permissions avant chaque action
- Ne jamais logger les tokens

### Tests
- Tester avec de vraies coordonnées GPS
- Tester en mode avion (erreurs réseau)
- Tester les timeouts

---

## 🚀 Prochaines étapes

1. ✅ Paiements Wave/Orange Money
2. ✅ Photos géolocalisées
3. 🔲 Workflow référent > 2M FCFA
4. 🔲 Fiche d'intervention + signature digitale
5. 🔲 Notifications push (Firebase)
6. 🔲 Mode hors-ligne (Hive/SQLite)

---

**Documentation créée le** : 7 mars 2026
**Auteur** : Claude Code Assistant
