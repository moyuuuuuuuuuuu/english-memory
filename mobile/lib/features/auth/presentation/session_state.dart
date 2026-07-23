import '../domain/auth_session.dart';

sealed class SessionState {
  const SessionState();
}

final class SessionRestoring extends SessionState {
  const SessionRestoring();
}

final class SessionSignedOut extends SessionState {
  const SessionSignedOut({this.prefilledIdentity, this.message});

  final String? prefilledIdentity;
  final String? message;
}

final class SessionAuthenticating extends SessionState {
  const SessionAuthenticating();
}

final class SessionAuthenticated extends SessionState {
  const SessionAuthenticated(this.session);

  final AuthSession session;
}

final class SessionOfflineError extends SessionState {
  const SessionOfflineError(this.message);

  final String message;
}
