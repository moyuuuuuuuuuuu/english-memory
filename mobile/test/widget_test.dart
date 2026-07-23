import 'package:english_memory/app/app_dependencies.dart';
import 'package:english_memory/app/english_memory_app.dart';
import 'package:english_memory/core/l10n/app_strings.dart';
import 'package:english_memory/core/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:english_memory/features/auth/data/auth_repository.dart';
import 'package:english_memory/features/auth/domain/auth_session.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:english_memory/features/auth/presentation/session_controller.dart';

import 'support/test_capture_controller.dart';

void main() {
  testWidgets('app starts on Home with the shared theme', (tester) async {
    final captureController = createTestCaptureController();
    addTearDown(captureController.dispose);
    await tester.pumpWidget(
      EnglishMemoryApp(
        dependencies: AppDependencies(
          controller: SessionController(_AuthenticatedRepository()),
          captureController: captureController,
        ),
      ),
    );
    await tester.pumpAndSettle();

    final app = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(app.title, AppStrings.appName);
    expect(app.theme!.scaffoldBackgroundColor, AppColors.warmWhite);
    expect(find.text(AppStrings.homeTitle), findsOneWidget);
  });
}

final class _AuthenticatedRepository implements AuthRepositoryGateway {
  @override
  Future<AuthSession?> restore() async => AuthSession(
    user: const AuthenticatedUser(
      id: 1,
      email: 'learner@example.com',
      username: null,
      timezone: 'Asia/Shanghai',
    ),
    credentials: SessionCredentials.fromTtl(
      accessToken: 'access',
      refreshToken: 'refresh',
      accessTtlSeconds: 900,
      refreshTtlSeconds: 2592000,
      now: DateTime.utc(2026),
    ),
  );

  @override
  Future<AuthSession> login({
    required String identity,
    required String password,
    required String deviceName,
  }) => throw UnimplementedError();

  @override
  Future<void> logout() async {}

  @override
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) => throw UnimplementedError();
}
