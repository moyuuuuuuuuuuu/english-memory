import 'package:english_memory/features/auth/domain/auth_session.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AuthenticatedUser', () {
    test('parses nullable identities and timezone', () {
      final user = AuthenticatedUser.fromJson({
        'id': 7,
        'email': 'learner@example.com',
        'username': null,
        'timezone': 'Asia/Shanghai',
      });

      expect(user.id, 7);
      expect(user.email, 'learner@example.com');
      expect(user.username, isNull);
      expect(user.timezone, 'Asia/Shanghai');
    });

    test('rejects invalid user data', () {
      expect(
        () => AuthenticatedUser.fromJson({'id': 0}),
        throwsFormatException,
      );
    });
  });

  group('SessionCredentials', () {
    final now = DateTime.utc(2026, 7, 23, 8);

    test('converts TTL values to UTC timestamps and round trips JSON', () {
      final credentials = SessionCredentials.fromTtl(
        accessToken: 'access-token',
        refreshToken: 'refresh-token',
        accessTtlSeconds: 900,
        refreshTtlSeconds: 2592000,
        now: now,
      );

      expect(credentials.accessExpiresAt, now.add(const Duration(minutes: 15)));
      expect(
        credentials.refreshExpiresAt,
        now.add(const Duration(days: 30)),
      );
      expect(SessionCredentials.fromJson(credentials.toJson()), credentials);
    });

    test('detects credentials inside the expiry skew', () {
      final credentials = SessionCredentials.fromTtl(
        accessToken: 'access-token',
        refreshToken: 'refresh-token',
        accessTtlSeconds: 900,
        refreshTtlSeconds: 2592000,
        now: now,
      );

      expect(
        credentials.isExpiring(now, const Duration(minutes: 5)),
        isFalse,
      );
      expect(
        credentials.isExpiring(
          now.add(const Duration(minutes: 11)),
          const Duration(minutes: 5),
        ),
        isTrue,
      );
    });

    test('rejects blank tokens and invalid TTL values', () {
      expect(
        () => SessionCredentials.fromTtl(
          accessToken: '',
          refreshToken: 'refresh',
          accessTtlSeconds: 900,
          refreshTtlSeconds: 2592000,
          now: now,
        ),
        throwsFormatException,
      );
      expect(
        () => SessionCredentials.fromTtl(
          accessToken: 'access',
          refreshToken: 'refresh',
          accessTtlSeconds: 0,
          refreshTtlSeconds: 2592000,
          now: now,
        ),
        throwsFormatException,
      );
    });
  });

  test('AuthSession combines a user and credentials', () {
    final user = AuthenticatedUser.fromJson({
      'id': 3,
      'email': null,
      'username': 'learner',
      'timezone': 'Asia/Shanghai',
    });
    final credentials = SessionCredentials.fromTtl(
      accessToken: 'access',
      refreshToken: 'refresh',
      accessTtlSeconds: 900,
      refreshTtlSeconds: 2592000,
      now: DateTime.utc(2026, 7, 23),
    );

    final session = AuthSession(user: user, credentials: credentials);

    expect(session.user, same(user));
    expect(session.credentials, same(credentials));
  });
}
