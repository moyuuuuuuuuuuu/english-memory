import 'dart:async';

import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:english_memory/features/auth/data/auth_repository.dart';
import 'package:english_memory/features/auth/domain/auth_session.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:english_memory/features/auth/presentation/session_controller.dart';
import 'package:english_memory/features/auth/presentation/session_state.dart';
import 'package:english_memory/features/auth/presentation/startup_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final credentials = SessionCredentials.fromTtl(
    accessToken: 'access',
    refreshToken: 'refresh',
    accessTtlSeconds: 900,
    refreshTtlSeconds: 2592000,
    now: DateTime.utc(2026, 7, 23, 8),
  );
  const user = AuthenticatedUser(
    id: 7,
    email: 'learner@example.com',
    username: null,
    timezone: 'Asia/Shanghai',
  );
  late AuthSession session;
  late FakeAuthRepository repository;
  late SessionController controller;

  setUp(() {
    session = AuthSession(user: user, credentials: credentials);
    repository = FakeAuthRepository();
    controller = SessionController(repository);
  });

  test('restore without credentials becomes signed out', () async {
    await controller.restore();

    expect(controller.state, isA<SessionSignedOut>());
  });

  test('restore with a valid session becomes authenticated', () async {
    repository.restoredSession = session;

    await controller.restore();

    expect((controller.state as SessionAuthenticated).session, session);
  });

  test('network restore failure preserves a retryable offline state', () async {
    repository.restoreError = const ApiException(
      code: 'NETWORK_ERROR',
      userMessage: '网络连接失败，请检查网络后重试。',
      isNetwork: true,
    );

    await controller.restore();

    final state = controller.state as SessionOfflineError;
    expect(state.message, '网络连接失败，请检查网络后重试。');
  });

  test('authentication restore rejection becomes signed out', () async {
    repository.restoreError = const ApiException(
      code: 'UNAUTHENTICATED',
      userMessage: '请重新登录。',
      statusCode: 401,
    );

    await controller.restore();

    expect(controller.state, isA<SessionSignedOut>());
  });

  test('retry restore re-enters restoration and authenticates', () async {
    repository.restoreError = const ApiException(
      code: 'NETWORK_ERROR',
      userMessage: '网络连接失败，请检查网络后重试。',
      isNetwork: true,
    );
    await controller.restore();
    repository
      ..restoreError = null
      ..restoredSession = session;

    await controller.retryRestore();

    expect(controller.state, isA<SessionAuthenticated>());
    expect(repository.restoreCalls, 2);
  });

  test('login transitions to authenticated and ignores duplicate submit', () async {
    final barrier = Completer<AuthSession>();
    repository.loginBarrier = barrier;

    final first = controller.login(
      identity: 'learner@example.com',
      password: 'SecurePass123!',
    );
    final second = controller.login(
      identity: 'learner@example.com',
      password: 'SecurePass123!',
    );
    expect(controller.state, isA<SessionAuthenticating>());
    expect(repository.loginCalls, 1);

    barrier.complete(session);
    await Future.wait([first, second]);

    expect(controller.state, isA<SessionAuthenticated>());
  });

  test('registration returns identity and success message to sign in', () async {
    repository.registration = const RegistrationResult(user);

    await controller.register(
      email: 'learner@example.com',
      password: 'SecurePass123!',
    );

    final state = controller.state as SessionSignedOut;
    expect(state.prefilledIdentity, 'learner@example.com');
    expect(state.message, contains('注册成功'));
  });

  test('logout always becomes signed out when remote logout fails', () async {
    repository
      ..restoredSession = session
      ..logoutError = const ApiException(
        code: 'NETWORK_ERROR',
        userMessage: '网络连接失败，请检查网络后重试。',
        isNetwork: true,
      );
    await controller.restore();

    await controller.logout();

    expect(controller.state, isA<SessionSignedOut>());
    expect(repository.logoutCalls, 1);
  });

  testWidgets('offline startup actions invoke retry and sign out', (tester) async {
    var retried = false;
    var signedOut = false;

    await tester.pumpWidget(
      MaterialApp(
        home: StartupPage(
          state: const SessionOfflineError('暂时无法连接服务器'),
          onRetry: () async => retried = true,
          onSignOut: () async => signedOut = true,
        ),
      ),
    );

    await tester.tap(find.text('重试'));
    await tester.pump();
    await tester.tap(find.text('退出登录'));
    await tester.pump();

    expect(retried, isTrue);
    expect(signedOut, isTrue);
  });
}

final class FakeAuthRepository implements AuthRepositoryGateway {
  AuthSession? restoredSession;
  RegistrationResult? registration;
  ApiException? restoreError;
  ApiException? logoutError;
  Completer<AuthSession>? loginBarrier;
  int restoreCalls = 0;
  int loginCalls = 0;
  int logoutCalls = 0;

  @override
  Future<AuthSession?> restore() async {
    restoreCalls++;
    if (restoreError case final error?) throw error;
    return restoredSession;
  }

  @override
  Future<AuthSession> login({
    required String identity,
    required String password,
    required String deviceName,
  }) async {
    loginCalls++;
    return loginBarrier == null
        ? restoredSession!
        : await loginBarrier!.future;
  }

  @override
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) async => registration!;

  @override
  Future<void> logout() async {
    logoutCalls++;
    if (logoutError case final error?) throw error;
  }
}
