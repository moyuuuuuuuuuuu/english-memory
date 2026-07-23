import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../domain/session_credentials.dart';
import 'session_store.dart';

final class SecureSessionStore implements SessionStore {
  const SecureSessionStore(
    this._storage, {
    this.key = defaultKey,
  });

  static const defaultKey = 'auth_session_v1';

  final FlutterSecureStorage _storage;
  final String key;

  @override
  Future<SessionCredentials?> read() async {
    final raw = await _storage.read(key: key);
    if (raw == null) {
      return null;
    }

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic> || decoded['version'] != 1) {
        throw const FormatException('Unsupported session version.');
      }
      final credentials = decoded['credentials'];
      if (credentials is! Map<String, dynamic>) {
        throw const FormatException('Invalid stored credentials.');
      }
      return SessionCredentials.fromJson(credentials);
    } on FormatException {
      await clear();
      return null;
    }
  }

  @override
  Future<void> write(SessionCredentials credentials) => _storage.write(
    key: key,
    value: jsonEncode({'version': 1, 'credentials': credentials.toJson()}),
  );

  @override
  Future<void> clear() => _storage.delete(key: key);
}
