import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../core/config/api_config.dart';
import '../core/database/local_app_database.dart';
import '../core/network/authenticated_api_client.dart';
import '../features/auth/data/auth_api.dart';
import '../features/auth/data/auth_repository.dart';
import '../features/auth/data/secure_session_store.dart';
import '../features/auth/presentation/session_controller.dart';
import '../features/auth/presentation/session_state.dart';
import '../features/capture/data/mlkit_text_recognition_service.dart';
import '../features/capture/data/plugin_image_crop_service.dart';
import '../features/capture/data/plugin_image_source_service.dart';
import '../features/capture/presentation/capture_controller.dart';
import '../features/sync/application/card_sync_coordinator.dart';
import '../features/sync/application/sync_lifecycle.dart';
import '../features/sync/data/card_outbox_repository.dart';
import '../features/sync/data/memory_card_api.dart';

final class AppDependencies {
  AppDependencies({
    required this.controller,
    required this.captureController,
    this.outbox,
    this.syncLifecycle,
  }) : authenticatedDio = null,
       _database = null,
       _coordinator = null,
       configurationMessage = null;

  AppDependencies._({
    required this.controller,
    required this.captureController,
    required this.authenticatedDio,
    required this.outbox,
    required this.syncLifecycle,
    required this._database,
    required this._coordinator,
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
      final database = LocalAppDatabase.defaults();
      final outbox = DriftCardOutboxRepository(database);
      final coordinator = CardSyncCoordinator(
        outbox,
        MemoryCardApi(protectedDio),
      );
      final sessionController = SessionController(
        repository,
        localData: outbox,
        syncLifecycle: coordinator,
      );
      final captureController = CaptureController(
        PluginImageSourceService(),
        PluginImageCropService(),
        MlKitTextRecognitionService(),
        saveDraft: (draft) async {
          final state = sessionController.state;
          if (state is! SessionAuthenticated) {
            throw StateError('Capture requires an authenticated account.');
          }
          final localId = await outbox.enqueue(state.session.user.id, draft);
          unawaited(coordinator.drain());
          return localId;
        },
      );
      return AppDependencies._(
        controller: sessionController,
        captureController: captureController,
        authenticatedDio: protectedDio,
        outbox: outbox,
        syncLifecycle: coordinator,
        database: database,
        coordinator: coordinator,
        configurationMessage: null,
      );
    } on FormatException {
      return AppDependencies._(
        controller: null,
        captureController: null,
        authenticatedDio: null,
        outbox: null,
        syncLifecycle: null,
        database: null,
        coordinator: null,
        configurationMessage: '缺少有效的 API_BASE_URL，请配置后重新启动。',
      );
    }
  }

  final SessionController? controller;
  final CaptureController? captureController;
  final Dio? authenticatedDio;
  final CardOutboxGateway? outbox;
  final SyncLifecycleGateway? syncLifecycle;
  final LocalAppDatabase? _database;
  final CardSyncCoordinator? _coordinator;
  final String? configurationMessage;

  void dispose() {
    controller?.dispose();
    captureController?.dispose();
    _coordinator?.dispose();
    unawaited(_database?.close());
    authenticatedDio?.close(force: true);
  }
}
