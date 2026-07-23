import 'package:drift/native.dart';
import 'package:english_memory/core/database/local_app_database.dart';
import 'package:english_memory/features/capture/domain/capture_content_type.dart';
import 'package:english_memory/features/capture/domain/capture_draft.dart';
import 'package:english_memory/features/capture/domain/capture_memory_style.dart';
import 'package:english_memory/features/sync/application/card_sync_coordinator.dart';
import 'package:english_memory/features/sync/data/card_outbox_repository.dart';
import 'package:english_memory/features/sync/data/memory_card_api.dart';
import 'package:english_memory/features/sync/domain/card_creation_accepted.dart';
import 'package:english_memory/features/sync/domain/pending_card_creation.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'replays the same key after remote success and local interruption',
    () async {
      final database = LocalAppDatabase.forTesting(NativeDatabase.memory());
      addTearDown(database.close);
      final now = DateTime.utc(2026, 7, 23, 8);
      final repository = DriftCardOutboxRepository(
        database,
        uuid: () => 'stable-local-id',
        now: () => now,
      );
      await repository.enqueue(
        7,
        CaptureDraft(
          text: 'ambition',
          contentType: CaptureContentType.word,
          memoryStyle: CaptureMemoryStyle.auto,
        ),
      );
      final interrupted = InterruptingOutbox(repository);
      final api = ReplayingApi();
      var coordinator = CardSyncCoordinator(interrupted, api, now: () => now);

      await coordinator.activate(7);
      coordinator.dispose();

      expect(api.localIds, ['stable-local-id']);
      expect(await repository.outstandingCount(7), 1);
      coordinator = CardSyncCoordinator(repository, api, now: () => now);
      addTearDown(coordinator.dispose);
      await coordinator.activate(7);

      expect(api.localIds, ['stable-local-id', 'stable-local-id']);
      expect(await repository.outstandingCount(7), 0);
      final card = await database.select(database.cachedCards).getSingle();
      expect(card.serverCardId, 11);
      expect(card.serverJobId, 22);
      expect(card.localStatus, CachedCardLocalStatus.queued);
    },
  );
}

final class ReplayingApi implements MemoryCardGateway {
  final localIds = <String>[];

  @override
  Future<CardCreationAccepted> create(PendingCardCreation item) async {
    localIds.add(item.localId);
    return CardCreationAccepted(
      cardId: 11,
      jobId: 22,
      status: 'queued',
      replayed: localIds.length > 1,
      dispatchPending: false,
    );
  }
}

final class InterruptingOutbox implements CardOutboxGateway {
  InterruptingOutbox(this.delegate);

  final CardOutboxGateway delegate;
  var interrupted = false;

  @override
  Future<void> complete(
    int accountId,
    String localId,
    CardCreationAccepted result,
  ) async {
    if (!interrupted) {
      interrupted = true;
      throw StateError('simulated process interruption');
    }
    await delegate.complete(accountId, localId, result);
  }

  @override
  Future<void> clearAccount(int accountId) => delegate.clearAccount(accountId);

  @override
  Future<String> enqueue(int accountId, CaptureDraft draft) =>
      delegate.enqueue(accountId, draft);

  @override
  Future<void> markBlocked(int accountId, String localId, String code) =>
      delegate.markBlocked(accountId, localId, code);

  @override
  Future<void> markRetry(
    int accountId,
    String localId, {
    required int attempts,
    required DateTime at,
    required String code,
  }) => delegate.markRetry(
    accountId,
    localId,
    attempts: attempts,
    at: at,
    code: code,
  );

  @override
  Future<void> markSending(int accountId, String localId) =>
      delegate.markSending(accountId, localId);

  @override
  Future<PendingCardCreation?> nextReady(int accountId, DateTime now) =>
      delegate.nextReady(accountId, now);

  @override
  Future<DateTime?> nextRetryAt(int accountId) =>
      delegate.nextRetryAt(accountId);

  @override
  Future<int> outstandingCount(int accountId) =>
      delegate.outstandingCount(accountId);

  @override
  Future<void> recoverSending(int accountId) =>
      delegate.recoverSending(accountId);

  @override
  Future<void> retryBlocked(int accountId, String localId) =>
      delegate.retryBlocked(accountId, localId);

  @override
  Stream<int> watchOutstandingCount(int accountId) =>
      delegate.watchOutstandingCount(accountId);
}
