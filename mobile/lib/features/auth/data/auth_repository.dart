import '../../../core/network/api_exception.dart';
import '../domain/auth_session.dart';
import '../domain/authenticated_user.dart';
import '../domain/session_credentials.dart';
import 'auth_api.dart';
import 'session_store.dart';
import 'session_token_provider.dart';

final class AuthRepository implements SessionTokenProvider {
  AuthRepository(
    this._api,
    this._store, {
    DateTime Function()? now,
    this.expirySkew = const Duration(minutes: 5),
  }) : _now = now ?? DateTime.now;

  final AuthGateway _api;
  final SessionStore _store;
  final DateTime Function() _now;
  final Duration expirySkew;

  SessionCredentials? _credentials;
  Future<SessionCredentials>? _refreshInFlight;

  @override
  SessionCredentials? get currentCredentials => _credentials;

  Future<AuthSession?> restore() async {
    final stored = await _store.read();
    if (stored == null) {
      return null;
    }

    _credentials = stored;
    try {
      final credentials = stored.accessExpiresAt.isBefore(
        _now().add(expirySkew),
      )
          ? await refreshCredentials()
          : stored;
      final user = await _api.currentUser(credentials.accessToken);
      return AuthSession(user: user, credentials: credentials);
    } on ApiException catch (error) {
      if (error.isAuthenticationFailure) {
        await invalidateSession();
      }
      rethrow;
    }
  }

  Future<AuthSession> login({
    required String identity,
    required String password,
    required String deviceName,
  }) async {
    final result = await _api.login(
      identity: identity.trim(),
      password: password,
      deviceName: deviceName,
    );
    await _store.write(result.credentials);
    _credentials = result.credentials;
    return AuthSession(user: result.user, credentials: result.credentials);
  }

  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) {
    return _api.register(
      email: email?.trim(),
      username: username?.trim(),
      password: password,
    );
  }

  Future<AuthenticatedUser> currentUser() async {
    final credentials = await _requireCredentials();
    return _api.currentUser(credentials.accessToken);
  }

  @override
  Future<SessionCredentials> refreshCredentials() {
    final existing = _refreshInFlight;
    if (existing != null) {
      return existing;
    }

    final running = _refreshAndReset();
    _refreshInFlight = running;
    return running;
  }

  Future<SessionCredentials> _refreshAndReset() async {
    try {
      final credentials = await _requireCredentials();
      final refreshed = await _api.refresh(credentials.refreshToken);
      await _store.write(refreshed);
      _credentials = refreshed;
      return refreshed;
    } on ApiException catch (error) {
      if (error.isAuthenticationFailure) {
        await invalidateSession();
      }
      rethrow;
    } finally {
      _refreshInFlight = null;
    }
  }

  Future<void> logout() async {
    try {
      final credentials = await _optionalCredentials();
      if (credentials != null) {
        try {
          await _api.logout(
            accessToken: credentials.accessToken,
            refreshToken: credentials.refreshToken,
          );
        } on ApiException {
          // Logout is best-effort; local credentials must always be removed.
        }
      }
    } finally {
      await invalidateSession();
    }
  }

  @override
  Future<void> invalidateSession() async {
    _credentials = null;
    await _store.clear();
  }

  Future<SessionCredentials> _requireCredentials() async {
    final credentials = await _optionalCredentials();
    if (credentials == null) {
      throw const ApiException(
        code: 'UNAUTHENTICATED',
        userMessage: '请重新登录。',
        statusCode: 401,
      );
    }
    return credentials;
  }

  Future<SessionCredentials?> _optionalCredentials() async {
    final current = _credentials;
    if (current != null) {
      return current;
    }
    final stored = await _store.read();
    _credentials = stored;
    return stored;
  }
}
