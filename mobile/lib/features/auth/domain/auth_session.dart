import 'authenticated_user.dart';
import 'session_credentials.dart';

final class AuthSession {
  const AuthSession({required this.user, required this.credentials});

  final AuthenticatedUser user;
  final SessionCredentials credentials;
}
