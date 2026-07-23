import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../core/config/api_config.dart';
import '../core/network/authenticated_api_client.dart';
import '../features/auth/data/auth_api.dart';
import '../features/auth/data/auth_repository.dart';
import '../features/auth/data/secure_session_store.dart';
import '../features/auth/presentation/session_controller.dart';

final class AppDependencies {
  AppDependencies({required this.controller})
    : authenticatedDio = null,
      configurationMessage = null;

  AppDependencies._({
    required this.controller,
    required this.authenticatedDio,
    required this.configurationMessage,
  });

  factory AppDependencies.production() {
    const configuredBaseUrl = String.fromEnvironment('API_BASE_URL');
    try {
      final config = ApiConfig.parse(configuredBaseUrl);
      final authDio = Dio(BaseOptions(baseUrl: config.baseUrl.toString()));
      final api = AuthApi(authDio);
      const secureStorage = FlutterSecureStorage();
      final repository = AuthRepository(
        api,
        const SecureSessionStore(secureStorage),
      );
      final protectedDio = Dio(BaseOptions(baseUrl: config.baseUrl.toString()));
      AuthenticatedApiClient(protectedDio, repository);
      return AppDependencies._(
        controller: SessionController(repository),
        authenticatedDio: protectedDio,
        configurationMessage: null,
      );
    } on FormatException {
      return AppDependencies._(
        controller: null,
        authenticatedDio: null,
        configurationMessage: '缺少有效的 API_BASE_URL，请配置后重新启动。',
      );
    }
  }

  final SessionController? controller;
  final Dio? authenticatedDio;
  final String? configurationMessage;

  void dispose() {
    controller?.dispose();
    authenticatedDio?.close(force: true);
  }
}
