// ============================================================================
// lib/main.dart
// ============================================================================

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:firebase_core/firebase_core.dart';

import '../core/config/app_config.dart';
import '../core/routes/app_routes.dart';
import '../core/routes/app_pages.dart';
import '../core/services/storage/hive_service.dart';
import '../core/services/notification/notification_service.dart';
import '../shared/bindings/initial_binding.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialiser Firebase
  await Firebase.initializeApp();
  
  // Initialiser Hive
  await Hive.initFlutter();
  await HiveService.init();
  
  // Initialiser les notifications
  await NotificationService.init();
  
  runApp(const ProsArtisanApp());
}

class ProsArtisanApp extends StatelessWidget {
  const ProsArtisanApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'ProsArtisan',
      debugShowCheckedModeBanner: false,
      theme: AppConfig.lightTheme,
      darkTheme: AppConfig.darkTheme,
      themeMode: ThemeMode.system,
      initialBinding: InitialBinding(),
      initialRoute: AppRoutes.SPLASH,
      getPages: AppPages.pages,
      locale: const Locale('fr', 'FR'),
      fallbackLocale: const Locale('fr', 'FR'),
    );
  }
}

// ============================================================================
// lib/core/config/app_config.dart
// ============================================================================

import 'package:flutter/material.dart';

class AppConfig {
  // API Configuration
  static const String API_BASE_URL = 'https://api.prosartisan.ci';
  static const String API_VERSION = 'v1';
  static const String API_URL = '$API_BASE_URL/api/$API_VERSION';
  
  // Map Configuration
  static const String GOOGLE_MAPS_API_KEY = 'YOUR_GOOGLE_MAPS_API_KEY';
  static const double DEFAULT_ZOOM = 14.0;
  static const double PROXIMITY_RADIUS_KM = 1.0;
  static const double GPS_BLUR_RADIUS_M = 50.0;
  
  // Payment Configuration
  static const double MATERIAL_RATIO = 0.65;
  static const double LABOR_RATIO = 0.35;
  static const int JETON_EXPIRY_DAYS = 7;
  
  // Score N'Zassa Weights
  static const double FIABILITY_WEIGHT = 0.40;
  static const double INTEGRITY_WEIGHT = 0.30;
  static const double QUALITY_WEIGHT = 0.20;
  static const double REACTIVITY_WEIGHT = 0.10;
  
  // Theme Configuration
  static ThemeData lightTheme = ThemeData(
    primarySwatch: Colors.blue,
    primaryColor: const Color(0xFF1E88E5),
    scaffoldBackgroundColor: Colors.white,
    fontFamily: 'Poppins',
    appBarTheme: const AppBarTheme(
      elevation: 0,
      backgroundColor: Colors.white,
      foregroundColor: Colors.black87,
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: const Color(0xFF1E88E5),
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
      ),
    ),
  );
  
  static ThemeData darkTheme = ThemeData.dark().copyWith(
    primaryColor: const Color(0xFF1E88E5),
    fontFamily: 'Poppins',
  );
}

// ============================================================================
// lib/core/routes/app_routes.dart
// ============================================================================

class AppRoutes {
  static const SPLASH = '/splash';
  static const ONBOARDING = '/onboarding';
  static const LOGIN = '/login';
  static const REGISTER = '/register';
  static const REGISTER_ARTISAN = '/register/artisan';
  static const KYC_VERIFICATION = '/kyc';
  
  // Main Navigation
  static const HOME = '/home';
  static const MARKETPLACE = '/marketplace';
  static const MISSIONS = '/missions';
  static const PROFILE = '/profile';
  
  // Marketplace
  static const ARTISAN_SEARCH = '/marketplace/search';
  static const ARTISAN_PROFILE = '/marketplace/artisan/:id';
  static const DEVIS_REQUEST = '/marketplace/devis/request';
  static const DEVIS_DETAIL = '/marketplace/devis/:id';
  
  // Mission
  static const MISSION_DETAIL = '/mission/:id';
  static const MISSION_CHAT = '/mission/:id/chat';
  
  // Payment
  static const PAYMENT_GATEWAY = '/payment';
  static const JETON_GENERATE = '/jeton/generate';
  static const JETON_SCAN = '/jeton/scan';
  
  // Worksite
  static const CHANTIER_DETAIL = '/chantier/:id';
  static const JALON_VALIDATION = '/chantier/:id/jalon/:jalonId';
  static const PREUVE_UPLOAD = '/chantier/:id/preuve';
  
  // Profile & Reputation
  static const SCORE_NZASSA = '/profile/score';
  static const EVALUATIONS = '/profile/evaluations';
  static const DOCUMENTS = '/profile/documents';
}

// ============================================================================
// lib/core/routes/app_pages.dart
// ============================================================================

import 'package:get/get.dart';
import '../../../features/auth/presentation/pages/splash_page.dart';
import '../../../features/auth/presentation/pages/login_page.dart';
import '../../../features/marketplace/presentation/pages/marketplace_page.dart';
import '../../routes/app_routes.dart';

class AppPages {
  static final pages = [
    GetPage(
      name: AppRoutes.SPLASH,
      page: () => const SplashPage(),
    ),
    GetPage(
      name: AppRoutes.LOGIN,
      page: () => const LoginPage(),
    ),
    GetPage(
      name: AppRoutes.MARKETPLACE,
      page: () => const MarketplacePage(),
    ),
    // ... autres pages
  ];
}

// ============================================================================
// lib/shared/bindings/initial_binding.dart
// ============================================================================

import 'package:get/get.dart';
import '../../../core/services/api/api_service.dart';
import '../../../core/services/storage/storage_service.dart';
import '../../../core/services/location/location_service.dart';
import '../../../features/auth/presentation/controllers/auth_controller.dart';

class InitialBinding extends Bindings {
  @override
  void dependencies() {
    // Services
    Get.put(ApiService(), permanent: true);
    Get.put(StorageService(), permanent: true);
    Get.put(LocationService(), permanent: true);
    
    // Controllers
    Get.put(AuthController(), permanent: true);
  }
}

// ============================================================================
// lib/core/services/storage/hive_service.dart
// ============================================================================

import 'package:hive_flutter/hive_flutter.dart';

class HiveService {
  static const String USER_BOX = 'user';
  static const String SETTINGS_BOX = 'settings';
  static const String CACHE_BOX = 'cache';
  
  static Future<void> init() async {
    await Hive.openBox(USER_BOX);
    await Hive.openBox(SETTINGS_BOX);
    await Hive.openBox(CACHE_BOX);
  }
  
  static Box getUserBox() => Hive.box(USER_BOX);
  static Box getSettingsBox() => Hive.box(SETTINGS_BOX);
  static Box getCacheBox() => Hive.box(CACHE_BOX);
  
  static Future<void> clearAll() async {
    await Hive.box(USER_BOX).clear();
    await Hive.box(CACHE_BOX).clear();
  }
}

// ============================================================================
// lib/core/services/api/api_service.dart
// ============================================================================

import 'package:dio/dio.dart';
import 'package:get/get.dart' as getx;
import 'package:pretty_dio_logger/pretty_dio_logger.dart';
import '../../../config/app_config.dart';
import '../../storage/storage_service.dart';

class ApiService extends getx.GetxService {
  late Dio _dio;
  
  @override
  void onInit() {
    super.onInit();
    _initDio();
  }
  
  void _initDio() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.API_URL,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));
    
    // Intercepteur pour le token
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = StorageService.to.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Token expiré, déconnecter l'utilisateur
          await StorageService.to.clearAuth();
          getx.Get.offAllNamed(AppRoutes.LOGIN);
        }
        return handler.next(error);
      },
    ));
    
    // Logger en développement
    _dio.interceptors.add(PrettyDioLogger(
      requestHeader: true,
      requestBody: true,
      responseHeader: false,
      responseBody: true,
    ));
  }
  
  Dio get dio => _dio;
  
  // Méthodes helper
  Future<Response> get(String path, {Map<String, dynamic>? params}) {
    return _dio.get(path, queryParameters: params);
  }
  
  Future<Response> post(String path, {dynamic data}) {
    return _dio.post(path, data: data);
  }
  
  Future<Response> put(String path, {dynamic data}) {
    return _dio.put(path, data: data);
  }
  
  Future<Response> delete(String path) {
    return _dio.delete(path);
  }
}

// ============================================================================
// lib/core/services/storage/storage_service.dart
// ============================================================================

import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

class StorageService extends GetxService {
  static StorageService get to => Get.find();
  
  late GetStorage _box;
  
  @override
  Future<void> onInit() async {
    super.onInit();
    await GetStorage.init();
    _box = GetStorage();
  }
  
  // Auth
  String? getToken() => _box.read('token');
  Future<void> saveToken(String token) => _box.write('token', token);
  
  Map<String, dynamic>? getUser() => _box.read('user');
  Future<void> saveUser(Map<String, dynamic> user) => _box.write('user', user);
  
  Future<void> clearAuth() async {
    await _box.remove('token');
    await _box.remove('user');
  }
  
  // Settings
  bool isDarkMode() => _box.read('darkMode') ?? false;
  Future<void> setDarkMode(bool value) => _box.write('darkMode', value);
  
  String getLanguage() => _box.read('language') ?? 'fr';
  Future<void> setLanguage(String lang) => _box.write('language', lang);
}