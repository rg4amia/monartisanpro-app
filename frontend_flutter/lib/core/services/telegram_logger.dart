import 'dart:async';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../config/env_config.dart';

class TelegramLogger {
  static final TelegramLogger _instance = TelegramLogger._internal();
  factory TelegramLogger() => _instance;
  TelegramLogger._internal();

  // Configuration Telegram Bot (depuis EnvConfig)
  String get _botToken => EnvConfig.telegramBotToken;
  
  String get _chatId => EnvConfig.telegramChatId;
  
  static const bool _enableInDebug = EnvConfig.telegramLoggerDebug;
  static const bool _enableInRelease = EnvConfig.telegramLoggerRelease;

  final Dio _dio = Dio(BaseOptions(
    connectTimeout: const Duration(seconds: 5),
    receiveTimeout: const Duration(seconds: 5),
  ));

  bool get _isEnabled {
    if (kDebugMode) return _enableInDebug;
    return _enableInRelease;
  }

  /// Envoie un message à Telegram
  Future<void> _sendMessage(String message) async {
    if (!_isEnabled || _botToken == 'YOUR_BOT_TOKEN') return;

    try {
      final url = 'https://api.telegram.org/bot$_botToken/sendMessage';
      await _dio.post(
        url,
        data: {
          'chat_id': _chatId,
          'text': message,
          'parse_mode': 'HTML',
        },
      );
    } catch (e) {
      // Silencieux pour éviter les boucles infinies
      debugPrint('TelegramLogger error: $e');
    }
  }

  /// Log une erreur
  Future<void> logError(
    dynamic error, {
    StackTrace? stackTrace,
    String? context,
  }) async {
    final buffer = StringBuffer();
    buffer.writeln('🔴 <b>ERREUR</b>');
    buffer.writeln('');
    
    if (context != null) {
      buffer.writeln('📍 <b>Contexte:</b> $context');
    }
    
    buffer.writeln('⚠️ <b>Erreur:</b>');
    buffer.writeln('<code>${_truncate(error.toString(), 500)}</code>');
    
    if (stackTrace != null) {
      buffer.writeln('');
      buffer.writeln('📚 <b>Stack:</b>');
      buffer.writeln('<code>${_truncate(stackTrace.toString(), 1000)}</code>');
    }
    
    buffer.writeln('');
    buffer.writeln('🕐 ${DateTime.now().toIso8601String()}');
    buffer.writeln('📱 ${Platform.operatingSystem}');

    await _sendMessage(buffer.toString());
  }

  /// Log une exception HTTP
  Future<void> logHttpError(
    DioException error, {
    String? context,
  }) async {
    final buffer = StringBuffer();
    buffer.writeln('🌐 <b>ERREUR HTTP</b>');
    buffer.writeln('');
    
    if (context != null) {
      buffer.writeln('📍 <b>Contexte:</b> $context');
    }
    
    buffer.writeln('🔗 <b>URL:</b> ${error.requestOptions.uri}');
    buffer.writeln('📤 <b>Méthode:</b> ${error.requestOptions.method}');
    
    if (error.response != null) {
      buffer.writeln('📥 <b>Status:</b> ${error.response?.statusCode}');
      buffer.writeln('');
      buffer.writeln('💬 <b>Réponse:</b>');
      buffer.writeln('<code>${_truncate(error.response?.data.toString() ?? 'N/A', 800)}</code>');
    } else {
      buffer.writeln('❌ <b>Type:</b> ${error.type}');
      buffer.writeln('💬 <b>Message:</b> ${error.message}');
    }
    
    buffer.writeln('');
    buffer.writeln('🕐 ${DateTime.now().toIso8601String()}');

    await _sendMessage(buffer.toString());
  }

  /// Log un warning
  Future<void> logWarning(String message, {String? context}) async {
    final buffer = StringBuffer();
    buffer.writeln('⚠️ <b>WARNING</b>');
    buffer.writeln('');
    
    if (context != null) {
      buffer.writeln('📍 <b>Contexte:</b> $context');
    }
    
    buffer.writeln('💬 $message');
    buffer.writeln('');
    buffer.writeln('🕐 ${DateTime.now().toIso8601String()}');

    await _sendMessage(buffer.toString());
  }

  /// Log une info (désactivé par défaut en production)
  Future<void> logInfo(String message, {String? context}) async {
    if (!kDebugMode) return;
    
    final buffer = StringBuffer();
    buffer.writeln('ℹ️ <b>INFO</b>');
    buffer.writeln('');
    
    if (context != null) {
      buffer.writeln('📍 <b>Contexte:</b> $context');
    }
    
    buffer.writeln('💬 $message');
    buffer.writeln('');
    buffer.writeln('🕐 ${DateTime.now().toIso8601String()}');

    await _sendMessage(buffer.toString());
  }

  /// Log un événement custom
  Future<void> logEvent(
    String event, {
    Map<String, dynamic>? data,
    String? context,
  }) async {
    final buffer = StringBuffer();
    buffer.writeln('📊 <b>EVENT: $event</b>');
    buffer.writeln('');
    
    if (context != null) {
      buffer.writeln('📍 <b>Contexte:</b> $context');
    }
    
    if (data != null && data.isNotEmpty) {
      buffer.writeln('📦 <b>Data:</b>');
      data.forEach((key, value) {
        buffer.writeln('  • $key: $value');
      });
    }
    
    buffer.writeln('');
    buffer.writeln('🕐 ${DateTime.now().toIso8601String()}');

    await _sendMessage(buffer.toString());
  }

  /// Tronque un texte trop long
  String _truncate(String text, int maxLength) {
    if (text.length <= maxLength) return text;
    return '${text.substring(0, maxLength)}...\n[tronqué]';
  }

  /// Configure le logger avec vos credentials
  static void configure({
    required String botToken,
    required String chatId,
  }) {
    // Note: Pour une vraie app, utilisez plutôt des variables d'environnement
    // ou un fichier de config sécurisé
    debugPrint('TelegramLogger configuré');
  }
}
