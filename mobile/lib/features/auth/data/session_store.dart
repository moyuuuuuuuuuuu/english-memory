import '../domain/session_credentials.dart';

abstract interface class SessionStore {
  Future<SessionCredentials?> read();

  Future<void> write(SessionCredentials credentials);

  Future<void> clear();
}
