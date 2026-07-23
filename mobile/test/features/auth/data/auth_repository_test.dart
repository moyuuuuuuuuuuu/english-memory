import 'dart:async';

import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:english_memory/features/auth/data/auth_repository.dart';
import 'package:english_memory/features/auth/data/session_store.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final now = DateTime.utc(2026, 7, 23, 8);
  const user = AuthenticatedUser(
    id: 4,
    email: 'learner@example.com',
    username: null,
    timezone: 'Asia/Shanghai',
  );

  SessionCredentials credentials(
    String suffix, {
    int accessTtl = 900,
  }) => SessionCredentials.fromTtl(
    accessToken: 'access-$suffix',
    refreshToken: 'refresh-$suffix',
    accessTtlSeconds: accessTtl,
    refreshTtlSeconds: 2592000,
    now: now,
  );

  late MemorySessionStore store;
  late FakeAuthGateway api;
  late AuthRepository repository;

  setUp(() {
    store = MemorySessionStore();
    api = FakeAuthGateway(user: user, credentials: credentials('rotated'));
    repository = AuthRepository(api, store, now: () => now);
  });

  test('restore returns null when no credentials are stored', () async {
    expect(await repository.restore(), isNull);
    expect(api.currentUserCalls, 0);
    expect(api.refreshCalls, 0);
  });

  test('restore validates a stored session with current user', () async {
    store.value = credentials('stored');

    final session = await repository.restore();

    expect(session!.user, user);
    expect(session.credentials, credentials('stored'));
    expect(api.currentUserTokens, ['access-stored']);
  });

  test('restore refreshes credentials inside the expiry skew first', () async {
    store.value = credentials('expiring', accessTtl: 120);

    final session = await repository.restore();

    expect(api.refreshTokens, ['refresh-expiring']);
    expect(store.value, credentials('rotated'));
    expect(session!.credentials, credentials('rotated'));
    expect(api.currentUserTokens, ['access-rotated']);
  });

  test('authentication rejection clears the stored session', () async {
    store.value = credentials('stored');
    api.currentUserError = const ApiException(
      code: 'UNAUTHENTICATED',
      userMessage: '请重新登录。',
      statusCode: 401,
    );

    await expectLater(repository.restore(), throwsA(isA<ApiException>()));

    expect(store.value, isNull);
    expect(repository.currentCredentials, isNull);
  });

  test('network restoration failure preserves credentials', () async {
    store.value = credentials('stored');
    api.currentUserError = const ApiException(
      code: 'NETWORK_ERROR',
      userMessage: '网络连接失败，请检查网络后重试。',
      isNetwork: true,
    );

    await expectLater(repository.restore(), throwsA(isA<ApiException>()));

    expect(store.value, credentials('stored'));
    expect(repository.currentCredentials, credentials('stored'));
  });

  test('login stores credentials and returns an authenticated session', () async {
    api.loginResult = LoginResult(
      user: user,
      credentials: credentials('login'),
    );

    final session = await repository.login(
      identity: ' learner@example.com ',
      password: 'SecurePass123!',
      deviceName: 'english-memory-mobile',
    );

    expect(api.loginIdentity, 'learner@example.com');
    expect(store.value, credentials('login'));
    expect(session.user, user);
  });

  test('registration does not create or persist a session', () async {
    final result = await repository.register(
      email: 'learner@example.com',
      password: 'SecurePass123!',
    );

    expect(result.user, user);
    expect(store.value, isNull);
    expect(repository.currentCredentials, isNull);
  });

  test('logout clears local credentials when the API call fails', () async {
    store.value = credentials('stored');
    await repository.restore();
    api.logoutError = const ApiException(
      code: 'NETWORK_ERROR',
      userMessage: '网络连接失败，请检查网络后重试。',
      isNetwork: true,
    );

    await repository.logout();

    expect(api.logoutCalls, 1);
    expect(store.value, isNull);
    expect(repository.currentCredentials, isNull);
  });

  test('concurrent refresh callers share one API request', () async {
    store.value = credentials('stored');
    await repository.restore();
    api.refreshBarrier = Completer<SessionCredentials>();

    final results = <Future<SessionCredentials>>[
      repository.refreshCredentials(),
      repository.refreshCredentials(),
      repository.refreshCredentials(),
    ];
    await Future<void>.delayed(Duration.zero);
    expect(api.refreshCalls, 1);

    api.refreshBarrier!.complete(credentials('rotated'));
    final values = await Future.wait(results);

    expect(values.toSet(), {credentials('rotated')});
    expect(store.value, credentials('rotated'));
  });
}

final class MemorySessionStore implements SessionStore {
  SessionCredentials? value;

  @override
  Future<void> clear() async => value = null;

  @override
  Future<SessionCredentials?> read() async => value;

  @override
  Future<void> write(SessionCredentials credentials) async => value = credentials;
}

final class FakeAuthGateway implements AuthGateway {
  FakeAuthGateway({required this.user, required this.credentials});

  final AuthenticatedUser user;
  final SessionCredentials credentials;
  LoginResult? loginResult;
  ApiException? currentUserError;
  ApiException? logoutError;
  Completer<SessionCredentials>? refreshBarrier;
  int currentUserCalls = 0;
  int refreshCalls = 0;
  int logoutCalls = 0;
  String? loginIdentity;
  final List<String> currentUserTokens = [];
  final List<String> refreshTokens = [];

  @override
  Future<AuthenticatedUser> currentUser(String accessToken) async {
    currentUserCalls++;
    currentUserTokens.add(accessToken);
    if (currentUserError case final error?) throw error;
    return user;
  }

  @override
  Future<LoginResult> login({
    required String identity,
    required String password,
    required String deviceName,
  }) async {
    loginIdentity = identity;
    return loginResult ?? LoginResult(user: user, credentials: credentials);
  }

  @override
  Future<void> logout({
    required String accessToken,
    required String refreshToken,
  }) async {
    logoutCalls++;
    if (logoutError case final error?) throw error;
  }

  @override
  Future<SessionCredentials> refresh(String refreshToken) {
    refreshCalls++;
    refreshTokens.add(refreshToken);
    return refreshBarrier?.future ?? Future.value(credentials);
  }

  @override
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) async => RegistrationResult(user);
}
