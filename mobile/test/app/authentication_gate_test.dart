import 'package:english_memory/app/authentication_gate.dart';
import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:english_memory/features/auth/data/auth_repository.dart';
import 'package:english_memory/features/auth/domain/auth_session.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:english_memory/features/auth/presentation/login_page.dart';
import 'package:english_memory/features/auth/presentation/session_controller.dart';
import 'package:english_memory/features/auth/presentation/startup_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('restores once and shows login without stored credentials', (
    tester,
  ) async {
    final repository = GateRepository();
    final controller = SessionController(repository);

    await tester.pumpWidget(
      MaterialApp(home: AuthenticationGate(controller: controller)),
    );
    expect(find.byType(StartupPage), findsOneWidget);
    await tester.pumpAndSettle();

    expect(repository.restoreCalls, 1);
    expect(find.byType(LoginPage), findsOneWidget);
  });

  testWidgets('shows the shell and authenticated identity', (tester) async {
    final repository = GateRepository()..session = testSession;
    final controller = SessionController(repository);

    await tester.pumpWidget(
      MaterialApp(home: AuthenticationGate(controller: controller)),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('我的'));
    await tester.pumpAndSettle();

    expect(find.text('learner@example.com'), findsOneWidget);
  });
}

final testSession = AuthSession(
  user: const AuthenticatedUser(
    id: 3,
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

final class GateRepository implements AuthRepositoryGateway {
  AuthSession? session;
  int restoreCalls = 0;

  @override
  Future<AuthSession?> restore() async {
    restoreCalls++;
    return session;
  }

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
