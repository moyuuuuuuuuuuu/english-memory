import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/network/api_exception.dart';
import '../../sync/application/sync_lifecycle.dart';
import '../data/auth_repository.dart';
import 'session_state.dart';

final class SessionController extends ChangeNotifier {
  SessionController(
    this._repository, {
    AccountLocalDataGateway? localData,
    SyncLifecycleGateway? syncLifecycle,
  }) : _localData = localData ?? const _NoopLocalData(),
       _syncLifecycle = syncLifecycle ?? const _NoopSyncLifecycle();

  static const deviceName = 'english-memory-mobile';

  final AuthRepositoryGateway _repository;
  final AccountLocalDataGateway _localData;
  final SyncLifecycleGateway _syncLifecycle;
  SessionState _state = const SessionRestoring();

  SessionState get state => _state;

  Future<void> restore() async {
    _setState(const SessionRestoring());
    try {
      final session = await _repository.restore();
      if (session != null) {
        unawaited(_syncLifecycle.activate(session.user.id));
      }
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
      unawaited(_syncLifecycle.activate(session.user.id));
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
      _syncLifecycle.deactivate();
      _setState(const SessionSignedOut());
    }
  }

  Future<void> confirmedLogout(int accountId) async {
    final current = _state;
    if (current is! SessionAuthenticated ||
        current.session.user.id != accountId) {
      return;
    }
    try {
      await _localData.clearAccount(accountId);
    } catch (_) {
      _setState(
        SessionAuthenticated(
          current.session,
          message: '无法清除本机数据，请重试。',
        ),
      );
      return;
    }
    await logout();
  }

  void _setState(SessionState value) {
    _state = value;
    notifyListeners();
  }
}

final class _NoopLocalData implements AccountLocalDataGateway {
  const _NoopLocalData();

  @override
  Future<void> clearAccount(int accountId) async {}
}

final class _NoopSyncLifecycle implements SyncLifecycleGateway {
  const _NoopSyncLifecycle();

  @override
  Future<void> activate(int accountId) async {}

  @override
  void deactivate() {}

  @override
  Future<void> drain() async {}

  @override
  Future<void> onResumed() async {}
}
