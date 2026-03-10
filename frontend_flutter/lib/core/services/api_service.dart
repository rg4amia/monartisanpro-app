import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response;

class ApiService extends GetxService {
  late Dio _dio;
  
  @override
  void onInit() {
    super.onInit();
    _initializeDio();
  }
  
  void _initializeDio() {
    _dio = Dio(BaseOptions(
      baseUrl: 'https://api.prosartisan.ci/api/v1',
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));
    
    // Add interceptors for logging, auth, etc.
    _dio.interceptors.add(LogInterceptor(
      requestBody: true,
      responseBody: true,
    ));
  }
  
  Dio get dio => _dio;
  
  // Artisan methods
  Future<Response> searchArtisans({
    required double latitude,
    required double longitude,
    String? category,
    int? tradeId,
    double radius = 5000, // 5km par défaut
  }) async {
    return await _dio.get('/artisans/search', queryParameters: {
      'lat': latitude,
      'lng': longitude,
      'radius': radius,
      if (category != null) 'category': category,
      if (tradeId != null) 'trade_id': tradeId,
    });
  }
  
  Future<Response> getArtisanProfile(int artisanId) async {
    return await _dio.get('/artisans/$artisanId');
  }
  
  // Mission methods
  Future<Response> createMission(Map<String, dynamic> data) async {
    return await _dio.post('/missions', data: data);
  }
  
  Future<Response> getMissions() async {
    return await _dio.get('/missions');
  }
  
  Future<Response> getMission(int missionId) async {
    return await _dio.get('/missions/$missionId');
  }
}