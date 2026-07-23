import 'package:flutter/foundation.dart';

import '../../../core/network/api_exception.dart';
import '../data/auth_repository.dart';
import 'session_state.dart';

final class SessionController extends ChangeNotifier {
  SessionController(this._repository);

  static const deviceName = 'english-memory-mobile';

  final AuthRepositoryGateway _repository;
  SessionState _state = const SessionRestoring();

  SessionState get state => _state;

  Future<void> restore() async {
    _setState(const SessionRestoring());
    try {
      final session = await _repository.restore();
      _setState(
        session == null
            ? const SessionSignedOut()
            : SessionAuthenticated(session),
      );
    } on ApiException catch (error) {
      if (error.isNetwork) {
        _setState(SessionOfflineError(error.userMessage));
      } else {
        _setState(const SessionSignedOut());
      }
    }
  }

  Future<void> retryRestore() => restore();

  Future<void> login({
    required String identity,
    required String password,
  }) async {
    if (_state is SessionAuthenticating) {
      return;
    }
    _setState(const SessionAuthenticating());
    try {
      final session = await _repository.login(
        identity: identity,
        password: password,
        deviceName: deviceName,
      );
      _setState(SessionAuthenticated(session));
    } on ApiException catch (error) {
      _setState(
        SessionSignedOut(
          prefilledIdentity: identity.trim(),
          message: error.userMessage,
        ),
      );
    }
  }

  Future<void> register({
    String? email,
    String? username,
    required String password,
  }) async {
    if (_state is SessionAuthenticating) {
      return;
    }
    _setState(const SessionAuthenticating());
    try {
      await _repository.register(
        email: email,
        username: username,
        password: password,
      );
      _setState(
        SessionSignedOut(
          prefilledIdentity: (email ?? username)?.trim(),
          message: '注册成功，请登录。',
        ),
      );
    } on ApiException catch (error) {
      _setState(
        SessionSignedOut(
          prefilledIdentity: (email ?? username)?.trim(),
          message: error.userMessage,
        ),
      );
    }
  }

  Future<void> logout() async {
    try {
      await _repository.logout();
    } on ApiException {
      // Local sign-out remains authoritative when the remote call fails.
    } finally {
      _setState(const SessionSignedOut());
    }
  }

  void _setState(SessionState value) {
    _state = value;
    notifyListeners();
  }
}
