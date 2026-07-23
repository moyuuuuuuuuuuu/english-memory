import '../domain/session_credentials.dart';

abstract interface class SessionTokenProvider {
  SessionCredentials? get currentCredentials;

  Future<SessionCredentials> refreshCredentials();

  Future<void> invalidateSession();
}
