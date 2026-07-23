import 'dart:async';

import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/features/capture/domain/capture_draft.dart';
import 'package:english_memory/features/sync/application/card_sync_coordinator.dart';
import 'package:english_memory/features/sync/application/retry_policy.dart';
import 'package:english_memory/features/sync/data/card_outbox_repository.dart';
import 'package:english_memory/features/sync/data/memory_card_api.dart';
import 'package:english_memory/features/sync/domain/card_creation_accepted.dart';
import 'package:english_memory/features/sync/domain/pending_card_creation.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final now = DateTime.utc(2026, 7, 23, 8);
  late FakeOutbox outbox;
  late FakeMemoryCardApi api;
  late FakeTimerFactory timers;
  late CardSyncCoordinator coordinator;

  setUp(() {
    outbox = FakeOutbox();
    api = FakeMemoryCardApi();
    timers = FakeTimerFactory();
    coordinator = CardSyncCoordinator(
      outbox,
      api,
      retryPolicy: RetryPolicy(now: () => now, jitter: (_) => Duration.zero),
      now: () => now,
      timerFactory: timers.call,
    );
  });

  tearDown(() => coordinator.dispose());

  test('activate recovers stale sending work then drains it', () async {
    outbox.add(item('one'), state: FakeState.sending);

    await coordinator.activate(7);

    expect(outbox.recoveredAccounts, [7]);
    expect(api.localIds, ['one']);
    expect(outbox.completedIds, ['one']);
  });

  test('concurrent drain calls share one in-flight API request', () async {
    outbox.add(item('one'));
    final response = Completer<CardCreationAccepted>();
    api.responses.add(response.future);

    final activation = coordinator.activate(7);
    await until(() => api.localIds.length == 1);
    final duplicate = coordinator.drain();

    expect(api.localIds, ['one']);
    response.complete(accepted());
    await Future.wait([activation, duplicate]);
    expect(api.localIds, ['one']);
  });

  test('transient failure schedules retry and stops this drain', () async {
    outbox
      ..add(item('one'))
      ..add(item('two'));
    api.responses.add(
      const ApiException(
        code: 'NETWORK_ERROR',
        userMessage: 'safe',
        isNetwork: true,
      ),
    );

    await coordinator.activate(7);

    expect(outbox.retryIds, ['one']);
    expect(outbox.retryAttempts, [1]);
    expect(outbox.stateOf('one'), FakeState.retryWait);
    expect(outbox.stateOf('two'), FakeState.pending);
    expect(timers.delays, [const Duration(seconds: 2)]);
  });

  test('activation restores a timer for an existing future retry', () async {
    outbox
      ..add(item('one'), state: FakeState.retryWait)
      ..nextRetry = now.add(const Duration(minutes: 2));

    await coordinator.activate(7);

    expect(api.localIds, isEmpty);
    expect(timers.delays, [const Duration(minutes: 2)]);
  });

  test('authentication failure pauses without increasing attempts', () async {
    outbox.add(item('one', attempts: 3));
    api.responses.add(
      const ApiException(
        code: 'UNAUTHENTICATED',
        userMessage: 'safe',
        statusCode: 401,
      ),
    );

    await coordinator.activate(7);

    expect(outbox.retryIds, isEmpty);
    expect(outbox.stateOf('one'), FakeState.pending);
    expect(timers.delays, isEmpty);
  });

  test('permanent error blocks one item and continues to the next', () async {
    outbox
      ..add(item('one'))
      ..add(item('two'));
    api.responses
      ..add(
        const ApiException(
          code: 'INVALID_INPUT',
          userMessage: 'safe',
          statusCode: 422,
        ),
      )
      ..add(accepted(cardId: 12, jobId: 23));

    await coordinator.activate(7);

    expect(outbox.blockedIds, ['one']);
    expect(outbox.stateOf('one'), FakeState.blocked);
    expect(outbox.completedIds, ['two']);
    expect(api.localIds, ['one', 'two']);
  });

  test(
    'local completion failure remains recoverable with the same key',
    () async {
      outbox
        ..add(item('one'))
        ..failCompleteOnce = true;

      await coordinator.activate(7);

      expect(outbox.stateOf('one'), FakeState.sending);
      expect(api.localIds, ['one']);

      coordinator.dispose();
      coordinator = CardSyncCoordinator(
        outbox,
        api,
        retryPolicy: RetryPolicy(now: () => now, jitter: (_) => Duration.zero),
        now: () => now,
        timerFactory: timers.call,
      );
      await coordinator.activate(7);

      expect(api.localIds, ['one', 'one']);
      expect(outbox.completedIds, ['one']);
    },
  );

  test('deactivate prevents an old response mutating the outbox', () async {
    outbox.add(item('one'));
    final response = Completer<CardCreationAccepted>();
    api.responses.add(response.future);

    final activation = coordinator.activate(7);
    await until(() => api.localIds.isNotEmpty);
    coordinator.deactivate();
    response.complete(accepted());
    await activation;

    expect(outbox.completedIds, isEmpty);
    expect(outbox.stateOf('one'), FakeState.sending);
  });
}

PendingCardCreation item(String id, {int attempts = 0}) => PendingCardCreation(
  localId: id,
  accountId: 7,
  text: id,
  contentType: 'word',
  memoryStyle: 'auto',
  attemptCount: attempts,
);

CardCreationAccepted accepted({int cardId = 11, int jobId = 22}) =>
    CardCreationAccepted(
      cardId: cardId,
      jobId: jobId,
      status: 'queued',
      replayed: false,
      dispatchPending: false,
    );

Future<void> until(bool Function() condition) async {
  for (var index = 0; index < 20 && !condition(); index++) {
    await Future<void>.delayed(Duration.zero);
  }
  expect(condition(), isTrue);
}

enum FakeState { pending, sending, retryWait, blocked }

final class FakeOutbox implements CardOutboxGateway {
  final items = <String, PendingCardCreation>{};
  final states = <String, FakeState>{};
  final recoveredAccounts = <int>[];
  final completedIds = <String>[];
  final retryIds = <String>[];
  final retryAttempts = <int>[];
  final blockedIds = <String>[];
  bool failCompleteOnce = false;
  DateTime? nextRetry;

  void add(PendingCardCreation value, {FakeState state = FakeState.pending}) {
    items[value.localId] = value;
    states[value.localId] = state;
  }

  FakeState? stateOf(String id) => states[id];

  @override
  Future<PendingCardCreation?> nextReady(int accountId, DateTime now) async {
    for (final entry in items.entries) {
      if (entry.value.accountId == accountId &&
          states[entry.key] == FakeState.pending) {
        return entry.value;
      }
    }
    return null;
  }

  @override
  Future<DateTime?> nextRetryAt(int accountId) async => nextRetry;

  @override
  Future<void> markSending(int accountId, String localId) async {
    states[localId] = FakeState.sending;
  }

  @override
  Future<void> complete(
    int accountId,
    String localId,
    CardCreationAccepted result,
  ) async {
    if (failCompleteOnce) {
      failCompleteOnce = false;
      throw StateError('simulated local crash');
    }
    completedIds.add(localId);
    items.remove(localId);
    states.remove(localId);
  }

  @override
  Future<void> markRetry(
    int accountId,
    String localId, {
    required int attempts,
    required DateTime at,
    required String code,
  }) async {
    retryIds.add(localId);
    retryAttempts.add(attempts);
    states[localId] = FakeState.retryWait;
  }

  @override
  Future<void> markBlocked(int accountId, String localId, String code) async {
    blockedIds.add(localId);
    states[localId] = FakeState.blocked;
  }

  @override
  Future<void> recoverSending(int accountId) async {
    recoveredAccounts.add(accountId);
    for (final entry in items.entries) {
      if (entry.value.accountId == accountId &&
          states[entry.key] == FakeState.sending) {
        states[entry.key] = FakeState.pending;
      }
    }
  }

  @override
  Future<void> retryBlocked(int accountId, String localId) async {
    states[localId] = FakeState.pending;
  }

  @override
  Future<String> enqueue(int accountId, CaptureDraft draft) =>
      throw UnimplementedError();

  @override
  Future<int> outstandingCount(int accountId) async => items.length;

  @override
  Stream<int> watchOutstandingCount(int accountId) =>
      Stream.value(items.length);

  @override
  Future<void> clearAccount(int accountId) async {
    final ids = items.values
        .where((item) => item.accountId == accountId)
        .map((item) => item.localId)
        .toList();
    for (final id in ids) {
      items.remove(id);
      states.remove(id);
    }
  }
}

final class FakeMemoryCardApi implements MemoryCardGateway {
  final responses = <Object>[];
  final localIds = <String>[];

  @override
  Future<CardCreationAccepted> create(PendingCardCreation item) async {
    localIds.add(item.localId);
    if (responses.isEmpty) return accepted();
    final response = responses.removeAt(0);
    if (response is Future<CardCreationAccepted>) return response;
    if (response is ApiException) throw response;
    return response as CardCreationAccepted;
  }
}

final class FakeTimerFactory {
  final delays = <Duration>[];
  final timers = <FakeSyncTimer>[];

  SyncTimer call(Duration delay, void Function() callback) {
    delays.add(delay);
    final timer = FakeSyncTimer(callback);
    timers.add(timer);
    return timer;
  }
}

final class FakeSyncTimer implements SyncTimer {
  FakeSyncTimer(this.callback);

  final void Function() callback;
  bool cancelled = false;

  @override
  void cancel() => cancelled = true;
}
