import 'dart:async';

import '../../../core/network/api_exception.dart';
import '../data/card_outbox_repository.dart';
import '../data/memory_card_api.dart';
import 'retry_policy.dart';

abstract interface class SyncTimer {
  void cancel();
}

typedef SyncTimerFactory =
    SyncTimer Function(Duration delay, void Function() callback);

final class CardSyncCoordinator {
  CardSyncCoordinator(
    this._outbox,
    this._api, {
    RetryPolicy? retryPolicy,
    DateTime Function()? now,
    SyncTimerFactory? timerFactory,
  }) : _retryPolicy = retryPolicy ?? RetryPolicy(),
       _now = now ?? DateTime.now,
       _timerFactory = timerFactory ?? _defaultTimer;

  final CardOutboxGateway _outbox;
  final MemoryCardGateway _api;
  final RetryPolicy _retryPolicy;
  final DateTime Function() _now;
  final SyncTimerFactory _timerFactory;

  int? _accountId;
  var _activation = 0;
  Future<void>? _drainInFlight;
  var _drainRequested = false;
  var _disposed = false;
  SyncTimer? _retryTimer;

  Future<void> activate(int accountId) async {
    if (_disposed) return;
    _retryTimer?.cancel();
    _retryTimer = null;
    _accountId = accountId;
    final activation = ++_activation;
    await _outbox.recoverSending(accountId);
    if (!_isActive(accountId, activation)) return;
    await drain();
  }

  Future<void> drain() {
    if (_disposed || _accountId == null) return Future.value();
    final current = _drainInFlight;
    if (current != null) {
      _drainRequested = true;
      return current;
    }

    late final Future<void> running;
    running = _drainLoop().whenComplete(() {
      if (identical(_drainInFlight, running)) {
        _drainInFlight = null;
      }
    });
    _drainInFlight = running;
    return running;
  }

  Future<void> _drainLoop() async {
    do {
      _drainRequested = false;
      await _drainReadyItems();
    } while (_drainRequested && !_disposed && _accountId != null);
  }

  Future<void> _drainReadyItems() async {
    final accountId = _accountId;
    if (accountId == null) return;
    final activation = _activation;

    while (_isActive(accountId, activation)) {
      final item = await _outbox.nextReady(accountId, _now().toUtc());
      if (item == null || !_isActive(accountId, activation)) return;
      await _outbox.markSending(accountId, item.localId);
      if (!_isActive(accountId, activation)) return;

      try {
        final result = await _api.create(item);
        if (!_isActive(accountId, activation)) return;
        await _outbox.complete(accountId, item.localId, result);
      } on ApiException catch (error) {
        if (!_isActive(accountId, activation)) return;
        final attempts = item.attemptCount + 1;
        final decision = _retryPolicy.decide(error, attempts: attempts);
        switch (decision.disposition) {
          case SyncFailureDisposition.retry:
            final nextAttemptAt = decision.nextAttemptAt!;
            await _outbox.markRetry(
              accountId,
              item.localId,
              attempts: attempts,
              at: nextAttemptAt,
              code: error.code,
            );
            if (_isActive(accountId, activation)) {
              _scheduleRetry(nextAttemptAt);
            }
            return;
          case SyncFailureDisposition.pauseAuthentication:
            await _outbox.recoverSending(accountId);
            return;
          case SyncFailureDisposition.block:
            await _outbox.markBlocked(accountId, item.localId, error.code);
            continue;
        }
      } catch (_) {
        // Keep `sending` durable. The next activation recovers it to pending
        // and safely replays the same idempotency key.
        return;
      }
    }
  }

  void _scheduleRetry(DateTime nextAttemptAt) {
    _retryTimer?.cancel();
    final delay = nextAttemptAt.difference(_now().toUtc());
    _retryTimer = _timerFactory(
      delay.isNegative ? Duration.zero : delay,
      () => unawaited(drain()),
    );
  }

  Future<void> onResumed() => drain();

  Future<void> retryBlocked(String localId) async {
    final accountId = _accountId;
    if (_disposed || accountId == null) return;
    await _outbox.retryBlocked(accountId, localId);
    await drain();
  }

  void deactivate() {
    _retryTimer?.cancel();
    _retryTimer = null;
    _accountId = null;
    _activation++;
    _drainRequested = false;
  }

  bool _isActive(int accountId, int activation) =>
      !_disposed && _accountId == accountId && _activation == activation;

  void dispose() {
    if (_disposed) return;
    deactivate();
    _disposed = true;
  }

  static SyncTimer _defaultTimer(Duration delay, void Function() callback) =>
      _DartSyncTimer(Timer(delay, callback));
}

final class _DartSyncTimer implements SyncTimer {
  const _DartSyncTimer(this._timer);

  final Timer _timer;

  @override
  void cancel() => _timer.cancel();
}
