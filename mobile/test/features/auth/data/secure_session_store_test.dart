import 'dart:convert';

import 'package:english_memory/features/auth/data/secure_session_store.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const storage = FlutterSecureStorage();
  late SecureSessionStore store;

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    store = SecureSessionStore(storage);
  });

  SessionCredentials credentials(String suffix) =>
      SessionCredentials.fromTtl(
        accessToken: 'access-$suffix',
        refreshToken: 'refresh-$suffix',
        accessTtlSeconds: 900,
        refreshTtlSeconds: 2592000,
        now: DateTime.utc(2026, 7, 23),
      );

  test('returns null when secure storage is empty', () async {
    expect(await store.read(), isNull);
  });

  test('round trips one versioned credential value', () async {
    final value = credentials('first');

    await store.write(value);

    expect(await store.read(), value);
    final raw = await storage.read(key: SecureSessionStore.defaultKey);
    final payload = jsonDecode(raw!) as Map<String, dynamic>;
    expect(payload['version'], 1);
    expect(payload['credentials'], isA<Map<String, dynamic>>());
    expect(raw, isNot(contains('password')));
  });

  test('replaces access and refresh credentials in one write', () async {
    await store.write(credentials('first'));
    final rotated = credentials('rotated');

    await store.write(rotated);

    expect(await store.read(), rotated);
    final raw = await storage.read(key: SecureSessionStore.defaultKey);
    expect(raw, contains('access-rotated'));
    expect(raw, contains('refresh-rotated'));
    expect(raw, isNot(contains('first')));
  });

  test('clears the stored value', () async {
    await store.write(credentials('first'));

    await store.clear();

    expect(await store.read(), isNull);
  });

  test('clears malformed and unsupported stored values', () async {
    for (final raw in <String>[
      'not-json',
      jsonEncode({'version': 2, 'credentials': {}}),
      jsonEncode({'version': 1, 'credentials': {'access_token': ''}}),
    ]) {
      await storage.write(key: SecureSessionStore.defaultKey, value: raw);

      expect(await store.read(), isNull);
      expect(
        await storage.read(key: SecureSessionStore.defaultKey),
        isNull,
      );
    }
  });
}
