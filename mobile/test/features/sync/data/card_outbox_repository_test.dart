import 'package:drift/drift.dart' hide isNull;
import 'package:drift/native.dart';
import 'package:english_memory/core/database/local_app_database.dart';
import 'package:english_memory/features/capture/domain/capture_content_type.dart';
import 'package:english_memory/features/capture/domain/capture_draft.dart';
import 'package:english_memory/features/capture/domain/capture_memory_style.dart';
import 'package:english_memory/features/sync/data/card_outbox_repository.dart';
import 'package:english_memory/features/sync/domain/card_creation_accepted.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final now = DateTime.utc(2026, 7, 23, 8);
  final draft = CaptureDraftFixture.value;
  late LocalAppDatabase database;
  late DriftCardOutboxRepository repository;
  var uuidIndex = 0;

  setUp(() {
    database = LocalAppDatabase.forTesting(NativeDatabase.memory());
    uuidIndex = 0;
    repository = DriftCardOutboxRepository(
      database,
      uuid: () => 'uuid-${++uuidIndex}',
      now: () => now,
    );
  });

  tearDown(() => database.close());

  test(
    'enqueue atomically creates a placeholder and stable outbox item',
    () async {
      final id = await repository.enqueue(7, draft);

      expect(id, 'uuid-1');
      expect(await repository.outstandingCount(7), 1);
      expect(await repository.outstandingCount(8), 0);

      final pending = await repository.nextReady(7, now);
      expect(pending?.localId, id);
      expect(pending?.accountId, 7);
      expect(pending?.text, 'A vivid memory.');
      expect(pending?.contentType, 'sentence');
      expect(pending?.memoryStyle, 'semantic_scene');
      expect(pending?.attemptCount, 0);

      final cards = await database.select(database.cachedCards).get();
      expect(cards, hasLength(1));
      expect(cards.single.localId, id);
      expect(cards.single.accountId, 7);
      expect(cards.single.localStatus, CachedCardLocalStatus.pending);
    },
  );

  test('nextReady is account scoped and ignores future retries', () async {
    await repository.enqueue(8, draft);
    final own = await repository.enqueue(7, draft);
    await repository.markRetry(
      7,
      own,
      attempts: 1,
      at: now.add(const Duration(minutes: 1)),
      code: 'NETWORK_ERROR',
    );

    expect(await repository.nextReady(7, now), isNull);
    expect((await repository.nextReady(8, now))?.accountId, 8);
    expect(
      (await repository.nextReady(
        7,
        now.add(const Duration(minutes: 1)),
      ))?.localId,
      own,
    );
  });

  test('recoverSending only restores the selected account', () async {
    final first = await repository.enqueue(7, draft);
    final second = await repository.enqueue(8, draft);
    await repository.markSending(7, first);
    await repository.markSending(8, second);

    await repository.recoverSending(7);

    expect((await repository.nextReady(7, now))?.localId, first);
    expect(await repository.nextReady(8, now), isNull);
  });

  test(
    'complete maps server ids and removes only the matching outbox',
    () async {
      final first = await repository.enqueue(7, draft);
      await repository.enqueue(8, draft);

      await repository.complete(
        7,
        first,
        const CardCreationAccepted(
          cardId: 11,
          jobId: 22,
          status: 'queued',
          replayed: false,
          dispatchPending: false,
        ),
      );

      expect(await repository.outstandingCount(7), 0);
      expect(await repository.outstandingCount(8), 1);
      final card = await (database.select(
        database.cachedCards,
      )..where((row) => row.localId.equals(first))).getSingle();
      expect(card.serverCardId, 11);
      expect(card.serverJobId, 22);
      expect(card.generationStatus, 'queued');
      expect(card.localStatus, CachedCardLocalStatus.queued);
    },
  );

  test(
    'complete rolls back outbox deletion when its placeholder is missing',
    () async {
      final id = await repository.enqueue(7, draft);
      await (database.delete(
        database.cachedCards,
      )..where((row) => row.localId.equals(id))).go();

      await expectLater(
        repository.complete(
          7,
          id,
          const CardCreationAccepted(
            cardId: 11,
            jobId: 22,
            status: 'queued',
            replayed: true,
            dispatchPending: false,
          ),
        ),
        throwsStateError,
      );

      expect(await repository.outstandingCount(7), 1);
    },
  );

  test('watchOutstandingCount reacts to account-scoped changes', () async {
    final values = <int>[];
    final subscription = repository.watchOutstandingCount(7).listen(values.add);
    await Future<void>.delayed(Duration.zero);

    await repository.enqueue(8, draft);
    await repository.enqueue(7, draft);
    await Future<void>.delayed(Duration.zero);

    expect(values.first, 0);
    expect(values.last, 1);
    expect(values, isNot(contains(2)));
    await subscription.cancel();
  });

  test(
    'blocked items can be manually restored without changing their key',
    () async {
      final id = await repository.enqueue(7, draft);
      await repository.markBlocked(7, id, 'INVALID_INPUT');

      expect(await repository.nextReady(7, now), isNull);
      expect(await repository.outstandingCount(7), 1);

      await repository.retryBlocked(7, id);

      expect((await repository.nextReady(7, now))?.localId, id);
    },
  );

  test('clearAccount removes only the selected account layers', () async {
    await repository.enqueue(7, draft);
    await repository.enqueue(8, draft);
    await database
        .into(database.syncStates)
        .insert(
          SyncStatesCompanion.insert(
            accountId: const Value(7),
            updatedAt: now,
            cursor: const Value(3),
          ),
        );
    await database
        .into(database.syncStates)
        .insert(
          SyncStatesCompanion.insert(
            accountId: const Value(8),
            updatedAt: now,
            cursor: const Value(4),
          ),
        );

    await repository.clearAccount(7);

    expect(await repository.outstandingCount(7), 0);
    expect(await repository.outstandingCount(8), 1);
    expect(
      await (database.select(
        database.cachedCards,
      )..where((row) => row.accountId.equals(7))).get(),
      isEmpty,
    );
    expect(
      await (database.select(
        database.syncStates,
      )..where((row) => row.accountId.equals(8))).get(),
      hasLength(1),
    );
  });

  test('duplicate uuid rolls back without a second placeholder', () async {
    repository = DriftCardOutboxRepository(
      database,
      uuid: () => 'same-id',
      now: () => now,
    );
    await repository.enqueue(7, draft);

    await expectLater(repository.enqueue(7, draft), throwsA(isA<Exception>()));

    expect(await database.select(database.cachedCards).get(), hasLength(1));
    expect(
      await database.select(database.pendingCardCreations).get(),
      hasLength(1),
    );
  });
}

abstract final class CaptureDraftFixture {
  static final value = CaptureDraft(
    text: ' A vivid memory. ',
    contentType: CaptureContentType.sentence,
    memoryStyle: CaptureMemoryStyle.semanticScene,
  );
}
